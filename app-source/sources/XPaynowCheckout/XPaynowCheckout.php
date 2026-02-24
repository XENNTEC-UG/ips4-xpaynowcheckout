<?php
/**
 * @brief		PayNow Checkout Gateway
 * @author      https://xenntec.com/
 */

namespace IPS\xpaynowcheckout;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * X PayNow Checkout Gateway
 */
class _XPaynowCheckout extends \IPS\nexus\Gateway
{
	/* !Features */
	const SUPPORTS_REFUNDS = TRUE;
	const SUPPORTS_PARTIAL_REFUNDS = FALSE;

	/**
	 * @brief	PayNow API base URL
	 */
	const API_BASE = 'https://api.paynow.gg/v1';

	/**
	 * @brief	Webhook event types required by this gateway. Single source of truth.
	 */
	const REQUIRED_WEBHOOK_EVENTS = array(
		'OnOrderCompleted',
		'OnRefund',
		'OnChargeback',
		'OnChargebackClosed',
		'OnSubscriptionActivated',
		'OnSubscriptionRenewed',
		'OnSubscriptionCanceled',
	);

	/**
	 * @brief	Webhook signature header name
	 */
	const SIGNATURE_HEADER = 'PayNow-Signature';

	/**
	 * @brief	Webhook timestamp header name
	 */
	const TIMESTAMP_HEADER = 'PayNow-Timestamp';

	/**
	 * @brief	Webhook timestamp tolerance in seconds (5 minutes)
	 */
	const TIMESTAMP_TOLERANCE = 300;

	/* !Payment Gateway */

	/**
	 * Authorize
	 *
	 * Creates a PayNow checkout session and redirects the customer to the hosted checkout.
	 *
	 * @param	\IPS\nexus\Transaction					$transaction	Transaction
	 * @param	array|\IPS\nexus\Customer\CreditCard	$values			Values from form OR stored card
	 * @param	\IPS\nexus\Fraud\MaxMind\Request|NULL	$maxMind		MaxMind request if enabled
	 * @return	\IPS\DateTime|NULL		Auth valid until or NULL for forever
	 * @throws	\LogicException			Message displayed to user
	 */
	public function auth( \IPS\nexus\Transaction $transaction, $values, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL, $recurrings = array(), $source = NULL )
	{
		$settings = json_decode( $this->settings, TRUE );
		if ( !\is_array( $settings ) )
		{
			throw new \LogicException( 'xpaynowcheckout_invalid_settings' );
		}

		$apiKey = isset( $settings['api_key'] ) ? \trim( (string) $settings['api_key'] ) : '';
		$storeId = isset( $settings['store_id'] ) ? \trim( (string) $settings['store_id'] ) : '';

		if ( $apiKey === '' OR $storeId === '' )
		{
			throw new \LogicException( 'xpaynowcheckout_missing_required_settings' );
		}

		/* Persist transaction so $transaction->id is available for metadata */
		$transaction->save();

		/* Resolve or create PayNow customer */
		$customerId = $this->getPaynowCustomer( $transaction, $settings );

		/* Build checkout lines from invoice items using inline_product (Stripe-style) */
		$lines = $this->buildPaynowLineItems( $transaction );

		/* Build return/cancel URLs */
		$returnUrl = !empty( $settings['return_url'] )
			? (string) $settings['return_url']
			: (string) $transaction->url()->setQueryString( 'pending', 1 );
		$cancelUrl = !empty( $settings['cancel_url'] )
			? (string) $settings['cancel_url']
			: (string) $transaction->invoice->checkoutUrl();

		/* Create checkout session */
		$checkoutBody = array(
			'customer_id'   => $customerId,
			'lines'         => $lines,
			'return_url'    => $returnUrl,
			'cancel_url'    => $cancelUrl,
			'auto_redirect' => TRUE,
			'metadata'      => array(
				'ips_transaction_id' => (string) (int) $transaction->id,
				'ips_invoice_id'     => (string) (int) $transaction->invoice->id,
				'ips_member_id'      => $transaction->member ? (string) (int) $transaction->member->member_id : '',
				'gateway_id'         => (string) (int) $this->id,
			),
		);

		try
		{
			$response = static::apiRequest( 'post', '/stores/' . $storeId . '/checkouts', $settings, $checkoutBody );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_auth' );
			throw new \LogicException( 'gateway_err' );
		}

		$checkoutUrl = ( \is_array( $response ) AND isset( $response['url'] ) AND \is_string( $response['url'] ) ) ? $response['url'] : '';
		if ( $checkoutUrl === '' )
		{
			throw new \LogicException( 'gateway_err' );
		}

		/* Store checkout session ID for webhook correlation */
		if ( isset( $response['id'] ) AND \is_string( $response['id'] ) )
		{
			$transaction->gw_id = $response['id'];
			$transaction->save();
		}

		/* Redirect to PayNow hosted checkout */
		\IPS\Output::i()->redirect( \IPS\Http\Url::external( $checkoutUrl ) );
		return NULL;
	}

	/**
	 * Check the gateway can process this amount/currency/address.
	 *
	 * @param	$amount			\IPS\nexus\Money		The amount
	 * @param	$billingAddress	\IPS\GeoLocation|NULL	Billing address
	 * @param	$customer		\IPS\nexus\Customer		The customer
	 * @param	array			$recurrings				Recurring cost details
	 * @return	bool
	 */
	public function checkValidity( \IPS\nexus\Money $amount, \IPS\GeoLocation $billingAddress = NULL, \IPS\nexus\Customer $customer = NULL, $recurrings = array() )
	{
		$settings = json_decode( $this->settings, TRUE );

		/* Block if essential settings are missing */
		if ( !\is_array( $settings )
			OR empty( $settings['api_key'] )
			OR empty( $settings['store_id'] ) )
		{
			return FALSE;
		}

		return parent::checkValidity( $amount, $billingAddress, $customer, $recurrings );
	}

	/* !ACP Configuration */

	/**
	 * Settings
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function settings( &$form )
	{
		$settings = json_decode( $this->settings, TRUE );
		if ( !\is_array( $settings ) )
		{
			$settings = array();
		}

		$form->addHeader( 'xpaynowcheckout_credentials' );
		$form->add( new \IPS\Helpers\Form\Text( 'xpaynowcheckout_api_key', isset( $settings['api_key'] ) ? $settings['api_key'] : NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'xpaynowcheckout_store_id', isset( $settings['store_id'] ) ? $settings['store_id'] : NULL, TRUE ) );
		$form->add( new \IPS\Helpers\Form\Text( 'xpaynowcheckout_default_product_id', isset( $settings['default_product_id'] ) ? $settings['default_product_id'] : NULL, FALSE ) );

		$form->addHeader( 'xpaynowcheckout_webhook' );
		$form->add( new \IPS\Helpers\Form\Url( 'xpaynowcheckout_webhook_url', isset( $settings['webhook_url'] ) ? $settings['webhook_url'] : NULL, FALSE, array(), NULL, NULL, NULL, 'xpaynowcheckout_webhook_url' ) );
		$form->add( new \IPS\Helpers\Form\Text( 'xpaynowcheckout_webhook_secret', isset( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : NULL, TRUE ) );

		$form->addHeader( 'xpaynowcheckout_checkout_settings' );
		$form->add( new \IPS\Helpers\Form\Url( 'xpaynowcheckout_return_url', isset( $settings['return_url'] ) ? $settings['return_url'] : NULL, FALSE ) );
		$form->add( new \IPS\Helpers\Form\Url( 'xpaynowcheckout_cancel_url', isset( $settings['cancel_url'] ) ? $settings['cancel_url'] : NULL, FALSE ) );

		$form->addHeader( 'xpaynowcheckout_fraud_protection' );
		$form->add( new \IPS\Helpers\Form\YesNo( 'xpaynowcheckout_chargeback_ban', isset( $settings['chargeback_ban'] ) ? $settings['chargeback_ban'] : TRUE, FALSE ) );

		$form->addHeader( 'xpaynowcheckout_replay' );
		$form->add( new \IPS\Helpers\Form\Number( 'xpaynowcheckout_replay_lookback', isset( $settings['replay_lookback'] ) ? (int) $settings['replay_lookback'] : 3600, FALSE, array( 'min' => 300, 'max' => 86400 ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'xpaynowcheckout_replay_overlap', isset( $settings['replay_overlap'] ) ? (int) $settings['replay_overlap'] : 300, FALSE, array( 'min' => 60, 'max' => 1800 ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'xpaynowcheckout_replay_max_events', isset( $settings['replay_max_events'] ) ? (int) $settings['replay_max_events'] : 100, FALSE, array( 'min' => 10, 'max' => 100 ) ) );
	}

	/**
	 * Test Settings
	 *
	 * Called when saving gateway in ACP. Validates API key, creates webhook if needed.
	 *
	 * @param	array	$settings	Settings to test
	 * @return	array				Updated settings
	 * @throws	\InvalidArgumentException
	 */
	public function testSettings( $settings )
	{
		if ( !\is_array( $settings ) )
		{
			$settings = array();
		}

		/* Normalize settings */
		$settings['api_key']            = isset( $settings['api_key'] ) ? \trim( (string) $settings['api_key'] ) : '';
		$settings['store_id']           = isset( $settings['store_id'] ) ? \trim( (string) $settings['store_id'] ) : '';
		$settings['default_product_id'] = isset( $settings['default_product_id'] ) ? \trim( (string) $settings['default_product_id'] ) : '';
		$settings['webhook_url']        = isset( $settings['webhook_url'] ) ? \trim( (string) $settings['webhook_url'] ) : '';
		$settings['webhook_secret']     = isset( $settings['webhook_secret'] ) ? \trim( (string) $settings['webhook_secret'] ) : '';
		$settings['webhook_secrets']    = isset( $settings['webhook_secrets'] ) ? (array) $settings['webhook_secrets'] : array();
		$settings['return_url']         = isset( $settings['return_url'] ) ? \trim( (string) $settings['return_url'] ) : '';
		$settings['cancel_url']         = isset( $settings['cancel_url'] ) ? \trim( (string) $settings['cancel_url'] ) : '';
		$settings['chargeback_ban']     = isset( $settings['chargeback_ban'] ) ? (bool) $settings['chargeback_ban'] : TRUE;
		$settings['replay_lookback']    = isset( $settings['replay_lookback'] ) ? \max( 300, \min( 86400, (int) $settings['replay_lookback'] ) ) : 3600;
		$settings['replay_overlap']     = isset( $settings['replay_overlap'] ) ? \max( 60, \min( 1800, (int) $settings['replay_overlap'] ) ) : 300;
		$settings['replay_max_events']  = isset( $settings['replay_max_events'] ) ? \max( 10, \min( 100, (int) $settings['replay_max_events'] ) ) : 100;

		if ( $settings['api_key'] === '' OR $settings['store_id'] === '' )
		{
			throw new \DomainException( 'xpaynowcheckout_missing_api_credentials' );
		}

		/* Validate API key by fetching products */
		try
		{
			static::apiRequest( 'get', '/stores/' . $settings['store_id'] . '/products?limit=1', $settings );
		}
		catch ( \Exception $e )
		{
			throw new \DomainException( 'xpaynowcheckout_invalid_api_credentials' );
		}

		/* Generate webhook URL if not yet set */
		if ( $settings['webhook_url'] === '' )
		{
			$settings['webhook_url'] = (string) \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=webhook&controller=webhook', 'front' );
		}

		/* Create webhook subscriptions if secrets not yet populated (one per event type) */
		if ( empty( $settings['webhook_secrets'] ) )
		{
			$secrets = array();
			foreach ( static::REQUIRED_WEBHOOK_EVENTS as $eventName )
			{
				try
				{
					$result = static::apiRequest( 'post', '/stores/' . $settings['store_id'] . '/webhooks', $settings, array(
						'url'           => $settings['webhook_url'],
						'subscribed_to' => $eventName,
					) );

					if ( isset( $result['secret'] ) AND \is_string( $result['secret'] ) AND $result['secret'] !== '' )
					{
						$secrets[] = $result['secret'];
					}
				}
				catch ( \Exception $e )
				{
					\IPS\Log::log( $e, 'xpaynowcheckout_webhook_create' );
				}
			}

			if ( !empty( $secrets ) )
			{
				$settings['webhook_secrets'] = $secrets;
			}
		}

		return $settings;
	}

	/**
	 * Refund
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction to refund
	 * @param	float|NULL				$amount			Amount (NULL for full)
	 * @return	mixed					Gateway refund reference ID
	 * @throws	\Exception
	 */
	public function refund( \IPS\nexus\Transaction $transaction, $amount = NULL, $reason = NULL )
	{
		// TODO: POST /v1/stores/{storeId}/orders/{orderId}/refund
		// PayNow only supports full order refund (no partial amount)
		// If $amount is provided and differs from full amount, log warning
		throw new \RuntimeException( 'PayNow refund not yet implemented.' );
	}

	/**
	 * Refund Reasons
	 *
	 * @return	array
	 */
	public static function refundReasons()
	{
		return array(
			'customer_request' => 'xpaynowcheckout_reason_customer_request',
			'duplicate'        => 'xpaynowcheckout_reason_duplicate',
			'fraudulent'       => 'xpaynowcheckout_reason_fraudulent',
		);
	}

	/**
	 * URL to view transaction in gateway dashboard
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	\IPS\Http\Url|NULL
	 */
	public function gatewayUrl( \IPS\nexus\Transaction $transaction )
	{
		$settings = json_decode( $this->settings, TRUE );
		if ( isset( $settings['store_id'] ) AND $transaction->gw_id )
		{
			return \IPS\Http\Url::external( "https://dashboard.paynow.gg/stores/{$settings['store_id']}/orders" );
		}

		return NULL;
	}

	/* !PayNow API Helpers */

	/**
	 * Make an authenticated API request to PayNow.
	 *
	 * @param	string	$method		HTTP method (GET, POST, PATCH, DELETE)
	 * @param	string	$path		API path (e.g., /stores/{id}/checkouts)
	 * @param	array	$settings	Gateway settings
	 * @param	array	$body		Request body (for POST/PATCH)
	 * @param	int		$timeout	Request timeout in seconds
	 * @return	array				Decoded JSON response
	 * @throws	\RuntimeException
	 */
	public static function apiRequest( $method, $path, array $settings, array $body = array(), $timeout = 20 )
	{
		$url = \IPS\Http\Url::external( static::API_BASE . $path );

		$request = $url->request( $timeout )
			->setHeaders( array(
				'Authorization' => 'apikey ' . $settings['api_key'],
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			) );

		$method = \mb_strtolower( $method );
		if ( $method === 'get' OR $method === 'delete' )
		{
			$response = $request->$method();
		}
		else
		{
			$response = $request->$method( json_encode( $body ) );
		}

		$decoded = $response->decodeJson();

		if ( isset( $decoded['status'] ) AND (int) $decoded['status'] >= 400 )
		{
			$errorMsg = isset( $decoded['message'] ) ? (string) $decoded['message'] : 'Unknown PayNow API error';
			throw new \RuntimeException( $errorMsg );
		}

		return $decoded;
	}

	/**
	 * Convert IPS Money to PayNow minor-unit integer safely (no float math).
	 *
	 * @param	\IPS\nexus\Money	$money	Money object
	 * @return	int
	 */
	protected function moneyToMinorUnit( \IPS\nexus\Money $money )
	{
		$decimals = \IPS\nexus\Money::numberOfDecimalsForCurrency( $money->currency );
		$multiplier = new \IPS\Math\Number( '1' . \str_repeat( '0', $decimals ) );
		$minor = $money->amount->multiply( $multiplier );

		return (int) (string) $minor;
	}

	/**
	 * Build PayNow checkout line items from invoice items using inline_product.
	 *
	 * Each IPS invoice item becomes a line with an inline product definition
	 * containing the item name and price. This mirrors the Stripe pattern of
	 * inline product_data per line item (stateless, no mapping table).
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @return	array
	 */
	protected function buildPaynowLineItems( \IPS\nexus\Transaction $transaction )
	{
		$lineItems = array();
		$itemIndex = 0;

		$invoiceLabel = \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_payment_invoice', FALSE, array( 'sprintf' => array( $transaction->invoice->id ) ) );
		\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $invoiceLabel );

		foreach ( $transaction->invoice->items as $invoiceItem )
		{
			if ( !isset( $invoiceItem->price ) OR !( $invoiceItem->price instanceof \IPS\nexus\Money ) )
			{
				continue;
			}

			$quantity = isset( $invoiceItem->quantity ) ? (int) $invoiceItem->quantity : 1;
			if ( $quantity < 1 )
			{
				$quantity = 1;
			}

			$unitAmount = $this->moneyToMinorUnit( $invoiceItem->price );
			if ( $unitAmount <= 0 )
			{
				continue;
			}

			$itemName = isset( $invoiceItem->name ) ? \trim( (string) $invoiceItem->name ) : '';
			if ( $itemName === '' )
			{
				$itemName = $invoiceLabel;
			}
			\IPS\Member::loggedIn()->language()->parseOutputForDisplay( $itemName );

			/* PayNow requires description 25-50000 chars */
			$itemDescription = $itemName . ' — ' . $invoiceLabel;
			if ( \mb_strlen( $itemDescription ) < 25 )
			{
				$itemDescription = \str_pad( $itemDescription, 25, '.' );
			}

			/* Unique slug per checkout to avoid PayNow "slug already in use" collisions */
			$itemSlug = 'ips-t' . $transaction->id . '-i' . $itemIndex;

			$lineItems[] = array(
				'inline_product' => array(
					'name'                    => $itemName,
					'slug'                    => $itemSlug,
					'description'             => $itemDescription,
					'price'                   => $unitAmount,
					'allow_one_time_purchase' => TRUE,
				),
				'quantity'     => $quantity,
				'subscription' => FALSE,
			);

			$itemIndex++;
		}

		/* Fallback: no items resolved — single summary line with total amount */
		if ( empty( $lineItems ) )
		{
			$fallbackAmount = $this->moneyToMinorUnit( $transaction->invoice->amountToPay() );

			$fallbackDescription = $invoiceLabel;
			if ( \mb_strlen( $fallbackDescription ) < 25 )
			{
				$fallbackDescription = \str_pad( $fallbackDescription, 25, '.' );
			}

			$lineItems[] = array(
				'inline_product' => array(
					'name'                    => $invoiceLabel,
					'slug'                    => 'ips-t' . $transaction->id . '-fallback',
					'description'             => $fallbackDescription,
					'price'                   => $fallbackAmount,
					'allow_one_time_purchase' => TRUE,
				),
				'quantity'     => 1,
				'subscription' => FALSE,
			);
		}

		return $lineItems;
	}

	/**
	 * Resolve or create PayNow customer for transaction member.
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @param	array					$settings		Gateway settings
	 * @return	string					PayNow customer ID
	 */
	protected function getPaynowCustomer( $transaction, $settings )
	{
		$member = $transaction->member;
		$profiles = $member->cm_profiles;
		$gatewayId = $this->id;

		/* Check if we already have a stored PayNow customer ID */
		if ( isset( $profiles[ $gatewayId ] ) AND !empty( $profiles[ $gatewayId ] ) )
		{
			return (string) $profiles[ $gatewayId ];
		}

		/* Create new customer via PayNow API */
		$customerBody = array(
			'name'     => (string) $member->name,
			'metadata' => array(
				'ips_member_id' => (string) (int) $member->member_id,
			),
		);

		$result = static::apiRequest( 'post', '/stores/' . $settings['store_id'] . '/customers', $settings, $customerBody );

		if ( !isset( $result['id'] ) )
		{
			throw new \RuntimeException( 'Failed to create PayNow customer.' );
		}

		$customerId = (string) $result['id'];

		/* Persist to cm_profiles for future lookups */
		$profiles[ $gatewayId ] = $customerId;
		$member->cm_profiles = $profiles;
		$member->save();

		return $customerId;
	}

	/**
	 * Verify PayNow webhook HMAC signature.
	 *
	 * @param	string	$payload		Raw request body
	 * @param	string	$signature		PayNow-Signature header value
	 * @param	string	$timestamp		PayNow-Timestamp header value (Unix ms)
	 * @param	string	$secret			Webhook signing secret
	 * @return	bool
	 */
	public static function verifyWebhookSignature( $payload, $signature, $timestamp, $secret )
	{
		if ( empty( $payload ) OR empty( $signature ) OR empty( $timestamp ) OR empty( $secret ) )
		{
			return FALSE;
		}

		// Verify timestamp freshness (5 minute tolerance)
		$timestampSec = (int) ( (int) $timestamp / 1000 );
		if ( \abs( \time() - $timestampSec ) > static::TIMESTAMP_TOLERANCE )
		{
			return FALSE;
		}

		// HMAC-SHA256 with base64 output (PayNow sends base64-encoded signatures)
		$signedPayload = $timestamp . '.' . $payload;
		$expectedSignature = \base64_encode( \hash_hmac( 'sha256', $signedPayload, $secret, TRUE ) );

		return \hash_equals( $expectedSignature, $signature );
	}

	/**
	 * Collect lightweight alert stats for AdminNotification checks.
	 *
	 * @return	array
	 */
	public static function collectAlertStats()
	{
		$stats = array(
			'webhook_error_count_24h' => 0,
			'replay_recent_run'       => FALSE,
			'mismatch_count_30d'      => 0,
			'replay_config_lookback'  => 3600,
		);

		/* Gateway settings */
		$gatewaySettings = NULL;
		foreach ( \IPS\nexus\Gateway::roots() as $gateway )
		{
			if ( $gateway instanceof static )
			{
				$gatewaySettings = \json_decode( $gateway->settings, TRUE );
				if ( !\is_array( $gatewaySettings ) )
				{
					$gatewaySettings = NULL;
				}
				break;
			}
		}

		if ( \is_array( $gatewaySettings ) AND isset( $gatewaySettings['replay_lookback'] ) )
		{
			$stats['replay_config_lookback'] = \max( 300, \min( 86400, (int) $gatewaySettings['replay_lookback'] ) );
		}

		/* Replay state */
		if ( isset( \IPS\Data\Store::i()->xpaynowcheckout_webhook_replay_state ) AND \is_array( \IPS\Data\Store::i()->xpaynowcheckout_webhook_replay_state ) )
		{
			$replayState = \IPS\Data\Store::i()->xpaynowcheckout_webhook_replay_state;
			$lastRunAt = ( isset( $replayState['last_run_at'] ) AND \is_numeric( $replayState['last_run_at'] ) ) ? (int) $replayState['last_run_at'] : NULL;
			$stats['replay_recent_run'] = ( $lastRunAt !== NULL AND ( \time() - $lastRunAt ) <= $stats['replay_config_lookback'] );
		}

		/* DB queries */
		$dayAgo = \time() - 86400;
		$monthAgo = \time() - ( 30 * 86400 );

		/* Respect acknowledgment timestamp */
		$ackAt = 0;
		if ( isset( \IPS\Data\Store::i()->pnc_webhook_errors_ack_at ) )
		{
			$ackAt = (int) \IPS\Data\Store::i()->pnc_webhook_errors_ack_at;
		}
		$errorsSince = \max( $dayAgo, $ackAt );

		try
		{
			$stats['webhook_error_count_24h'] = (int) \IPS\Db::i()->select(
				'COUNT(*)',
				'core_log',
				array( '( category=? OR category=? ) AND time>?', 'xpaynowcheckout_webhook', 'xpaynowcheckout_snapshot', $errorsSince )
			)->first();
		}
		catch ( \Exception $e ) {}

		try
		{
			$mismatchWhere = "JSON_UNQUOTE(JSON_EXTRACT(t_extra, '$.xpaynowcheckout_snapshot.has_total_mismatch'))='true'";
			$stats['mismatch_count_30d'] = (int) \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( "{$mismatchWhere} AND t_date>?", $monthAgo ) )->first();
		}
		catch ( \Exception $e ) {}

		return $stats;
	}

	/**
	 * Fetch current webhook endpoint from PayNow.
	 *
	 * @param	array	$settings	Gateway settings
	 * @return	array|NULL			Webhook subscription object
	 */
	public static function fetchWebhookEndpoint( array $settings )
	{
		if ( empty( $settings['api_key'] ) OR empty( $settings['store_id'] ) )
		{
			return NULL;
		}

		try
		{
			/* Fast path: webhook ID stored */
			if ( !empty( $settings['webhook_id'] ) )
			{
				$webhooks = static::apiRequest( 'get', '/stores/' . $settings['store_id'] . '/webhooks', $settings );
				if ( \is_array( $webhooks ) )
				{
					foreach ( $webhooks as $wh )
					{
						if ( isset( $wh['id'] ) AND $wh['id'] === $settings['webhook_id'] )
						{
							return $wh;
						}
					}
				}
			}

			/* Fallback: find by URL match */
			if ( !empty( $settings['webhook_url'] ) )
			{
				$webhooks = static::apiRequest( 'get', '/stores/' . $settings['store_id'] . '/webhooks', $settings );
				if ( \is_array( $webhooks ) )
				{
					foreach ( $webhooks as $wh )
					{
						if ( isset( $wh['url'] ) AND $wh['url'] === $settings['webhook_url'] )
						{
							return $wh;
						}
					}
				}
			}
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_webhook_sync' );
		}

		return NULL;
	}
}
