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
		// TODO: Implement PayNow checkout session creation
		// 1. Resolve or create PayNow customer for the IPS member
		// 2. Build checkout lines from invoice items
		// 3. POST /v1/stores/{storeId}/checkouts
		// 4. Redirect to checkout URL or open paynow.js overlay
		throw new \DomainException( 'PayNow Checkout gateway is not yet implemented.' );
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
		// TODO: Validate currency against PayNow store settings
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

		$form->addHeader( 'xpaynowcheckout_webhook' );
		$form->add( new \IPS\Helpers\Form\Text( 'xpaynowcheckout_webhook_url', isset( $settings['webhook_url'] ) ? $settings['webhook_url'] : NULL, FALSE, array( 'disabled' => !isset( $settings['webhook_url'] ) ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'xpaynowcheckout_webhook_secret', isset( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : NULL, FALSE, array( 'disabled' => !isset( $settings['webhook_secret'] ) ) ) );

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

		// TODO: Validate API key by calling GET /v1/stores/{storeId}/products?limit=1
		// TODO: Create webhook endpoint if webhook_url and webhook_secret are empty
		//       POST /v1/stores/{storeId}/webhooks with required events
		// TODO: Store webhook_id, webhook_url, webhook_secret in settings

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
	 * Resolve or create PayNow customer for transaction member.
	 *
	 * @param	\IPS\nexus\Transaction	$transaction	Transaction
	 * @param	array					$settings		Gateway settings
	 * @return	string					PayNow customer ID
	 */
	protected function getPaynowCustomer( $transaction, $settings )
	{
		// TODO: Check cm_profiles for existing PayNow customer ID
		// TODO: If not found, POST /v1/stores/{storeId}/customers to create
		// TODO: Store PayNow customer ID in cm_profiles
		throw new \RuntimeException( 'PayNow customer resolution not yet implemented.' );
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
