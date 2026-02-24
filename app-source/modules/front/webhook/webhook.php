<?php
/**
 * @brief		PayNow Webhook Endpoint
 * @author      https://xenntec.com/
 */

namespace IPS\xpaynowcheckout\modules\front\webhook;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PayNow Webhook Controller
 */
class _webhook extends \IPS\Dispatcher\Controller
{
	/**
	 * Default action — receives webhook POST from PayNow.
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* Only accept POST */
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' )
		{
			\IPS\Output::i()->json( array( 'error' => 'method_not_allowed' ), 405 );
			return;
		}

		$rawPayload = file_get_contents( 'php://input' );

		/* Get signature headers */
		$signature = isset( $_SERVER['HTTP_PAYNOW_SIGNATURE'] ) ? $_SERVER['HTTP_PAYNOW_SIGNATURE'] : '';
		$timestamp = isset( $_SERVER['HTTP_PAYNOW_TIMESTAMP'] ) ? $_SERVER['HTTP_PAYNOW_TIMESTAMP'] : '';

		/* Load gateway settings */
		$gateway = NULL;
		$settings = array();
		foreach ( \IPS\nexus\Gateway::roots() as $gw )
		{
			if ( $gw instanceof \IPS\xpaynowcheckout\XPaynowCheckout )
			{
				$gateway = $gw;
				$settings = json_decode( $gw->settings, TRUE );
				if ( !\is_array( $settings ) )
				{
					$settings = array();
				}
				break;
			}
		}

		if ( !$gateway )
		{
			$this->logForensic( 'invalid_gateway', '', '', '', \IPS\Request::i()->ipAddress(), 0, $rawPayload );
			\IPS\Output::i()->json( array( 'error' => 'gateway_not_found' ), 400 );
			return;
		}

		/* Validate signature — try all stored webhook secrets (PayNow uses one subscription per event, each with own secret) */
		$webhookSecrets = isset( $settings['webhook_secrets'] ) ? (array) $settings['webhook_secrets'] : array();
		if ( !empty( $settings['webhook_secret'] ) )
		{
			$webhookSecrets[] = $settings['webhook_secret'];
		}
		$webhookSecrets = \array_unique( \array_filter( $webhookSecrets ) );

		if ( empty( $webhookSecrets ) )
		{
			$this->logForensic( 'missing_secret', '', '', '', \IPS\Request::i()->ipAddress(), 0, $rawPayload );
			\IPS\Output::i()->json( array( 'error' => 'webhook_not_configured' ), 500 );
			return;
		}

		$signatureValid = FALSE;
		foreach ( $webhookSecrets as $secret )
		{
			if ( \IPS\xpaynowcheckout\XPaynowCheckout::verifyWebhookSignature( $rawPayload, $signature, $timestamp, $secret ) )
			{
				$signatureValid = TRUE;
				break;
			}
		}

		if ( !$signatureValid )
		{
			$failureReason = empty( $signature ) ? 'missing_signature' : 'invalid_signature';
			if ( !empty( $signature ) AND !empty( $timestamp ) )
			{
				$tsSec = (int) ( (int) $timestamp / 1000 );
				if ( \abs( \time() - $tsSec ) > \IPS\xpaynowcheckout\XPaynowCheckout::TIMESTAMP_TOLERANCE )
				{
					$failureReason = 'timestamp_too_old';
				}
			}
			$this->logForensic( $failureReason, '', '', $signature, \IPS\Request::i()->ipAddress(), 0, $rawPayload );
			\IPS\Output::i()->json( array( 'error' => 'invalid_signature' ), 401 );
			return;
		}

		/* Parse payload — PayNow webhook structure: { event_type, event_id, body } */
		$payload = json_decode( $rawPayload, TRUE );
		if ( !\is_array( $payload ) )
		{
			$this->logForensic( 'invalid_payload', '', '', $signature, \IPS\Request::i()->ipAddress(), 0, $rawPayload );
			\IPS\Output::i()->json( array( 'error' => 'invalid_payload' ), 400 );
			return;
		}

		$eventType = isset( $payload['event_type'] ) ? (string) $payload['event_type'] : '';
		$eventId = isset( $payload['event_id'] ) ? (string) $payload['event_id'] : '';
		$eventBody = ( isset( $payload['body'] ) && \is_array( $payload['body'] ) ) ? $payload['body'] : array();

		try
		{
			$this->processEvent( $eventType, $eventBody, $eventId, $gateway, $settings );
			\IPS\Output::i()->json( array( 'ok' => TRUE ), 200 );
		}
		catch ( \Exception $e )
		{
			$this->logForensic( 'processing_error', $eventType, $eventId, $signature, \IPS\Request::i()->ipAddress(), 500, $rawPayload );
			\IPS\Log::log( $e, 'xpaynowcheckout_webhook' );
			\IPS\Output::i()->json( array( 'error' => 'processing_error' ), 500 );
		}
	}

	/**
	 * Process a PayNow webhook event.
	 *
	 * PayNow incoming webhooks use SCREAMING_SNAKE_CASE event types (e.g. ON_ORDER_COMPLETED).
	 * The management API subscribed_to field uses PascalCase (e.g. OnOrderCompleted).
	 *
	 * @param	string							$eventType	Event type string (SCREAMING_SNAKE_CASE)
	 * @param	array							$body		Event body payload (inside the "body" key)
	 * @param	string							$eventId	PayNow event ID (Flake ID)
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway	Gateway instance
	 * @param	array							$settings	Gateway settings
	 * @return	void
	 */
	protected function processEvent( $eventType, array $body, $eventId, $gateway, array $settings )
	{
		switch ( $eventType )
		{
			case 'ON_ORDER_COMPLETED':
				$this->handleOrderCompleted( $body, $eventId, $gateway, $settings );
				break;

			case 'ON_REFUND':
				$this->handleRefund( $body, $eventId, $gateway, $settings );
				break;

			case 'ON_CHARGEBACK':
				$this->handleChargeback( $body, $eventId, $gateway, $settings );
				break;

			case 'ON_CHARGEBACK_CLOSED':
				$this->handleChargebackClosed( $body, $eventId, $gateway, $settings );
				break;

			case 'ON_SUBSCRIPTION_ACTIVATED':
			case 'ON_SUBSCRIPTION_RENEWED':
			case 'ON_SUBSCRIPTION_CANCELED':
				$this->handleSubscriptionEvent( $eventType, $body, $eventId, $gateway, $settings );
				break;

			default:
				\IPS\Log::log( 'Unhandled PayNow webhook event: ' . $eventType, 'xpaynowcheckout_webhook' );
				break;
		}
	}

	/**
	 * Handle ON_ORDER_COMPLETED — approve the pending transaction.
	 *
	 * @param	array	$body		Order data from webhook body
	 * @param	string	$eventId	PayNow event ID
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleOrderCompleted( array $body, $eventId, $gateway, array $settings )
	{
		/* Extract IPS transaction ID from metadata */
		$transactionId = NULL;
		if ( isset( $body['metadata']['ips_transaction_id'] ) )
		{
			$transactionId = (int) $body['metadata']['ips_transaction_id'];
		}
		elseif ( isset( $body['checkout']['metadata']['ips_transaction_id'] ) )
		{
			$transactionId = (int) $body['checkout']['metadata']['ips_transaction_id'];
		}
		/* Fallback: match by checkout_id stored as gw_id in auth() */
		elseif ( isset( $body['checkout_id'] ) )
		{
			try
			{
				$transactionId = (int) \IPS\Db::i()->select( 't_id', 'nexus_transactions', array( 't_gw_id=? AND t_method=?', (string) $body['checkout_id'], $gateway->id ) )->first();
			}
			catch ( \UnderflowException $e ) {}
		}

		if ( !$transactionId )
		{
			\IPS\Log::log( 'ON_ORDER_COMPLETED: No ips_transaction_id in metadata. Order: ' . ( isset( $body['id'] ) ? $body['id'] : 'unknown' ), 'xpaynowcheckout_webhook' );
			return;
		}

		/* Load transaction */
		try
		{
			$transaction = \IPS\nexus\Transaction::load( $transactionId );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Log::log( 'ON_ORDER_COMPLETED: Transaction not found: ' . $transactionId, 'xpaynowcheckout_webhook' );
			return;
		}

		/* Idempotency — skip if already in a terminal state */
		if ( $transaction->status === \IPS\nexus\Transaction::STATUS_PAID
			OR $transaction->status === \IPS\nexus\Transaction::STATUS_PART_REFUNDED
			OR $transaction->status === \IPS\nexus\Transaction::STATUS_REFUNDED )
		{
			return;
		}

		/* Build and persist settlement snapshot */
		$snapshot = $this->buildPaynowSnapshot( $body, $transaction );
		$extra = $transaction->extra;
		if ( !\is_array( $extra ) )
		{
			$extra = array();
		}
		$extra['xpaynowcheckout_snapshot'] = $snapshot;
		$transaction->extra = $extra;

		/* Update gw_id from checkout session ID to PayNow order ID */
		if ( isset( $body['id'] ) AND \is_string( $body['id'] ) )
		{
			$transaction->gw_id = $body['id'];
		}
		$transaction->save();

		/* Persist snapshot to invoice status_extra */
		try
		{
			$invoice = $transaction->invoice;
			$statusExtra = $invoice->status_extra;
			if ( !\is_array( $statusExtra ) )
			{
				$statusExtra = array();
			}
			$statusExtra['xpaynowcheckout_snapshot'] = $snapshot;
			$invoice->status_extra = $statusExtra;
			$invoice->save();
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_snapshot' );
		}

		/* Approve transaction — triggers markPaid on invoice when fully paid */
		$maxMind = NULL;
		if ( \IPS\Settings::i()->maxmind_key )
		{
			$maxMind = new \IPS\nexus\Fraud\MaxMind\Request;
			$maxMind->setTransaction( $transaction );
		}
		$transaction->checkFraudRulesAndCapture( $maxMind );
	}

	/**
	 * Handle ON_REFUND — record refund on the IPS transaction.
	 *
	 * @param	array	$body		Refund data (includes order_id, payment_id, amount, etc.)
	 * @param	string	$eventId	PayNow event ID
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleRefund( array $body, $eventId, $gateway, array $settings )
	{
		// TODO: Find matching IPS transaction via $body['order_id']
		// TODO: Record refund status
	}

	/**
	 * Handle ON_CHARGEBACK — ban member if configured.
	 *
	 * @param	array	$body		Chargeback data (includes order_id, amount, status, etc.)
	 * @param	string	$eventId	PayNow event ID
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleChargeback( array $body, $eventId, $gateway, array $settings )
	{
		// TODO: Find matching IPS transaction via $body['order_id']
		// TODO: If chargeback_ban enabled, ban the member
		// TODO: Update transaction status
	}

	/**
	 * Handle ON_CHARGEBACK_CLOSED.
	 *
	 * @param	array	$body		Chargeback resolution data
	 * @param	string	$eventId	PayNow event ID
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleChargebackClosed( array $body, $eventId, $gateway, array $settings )
	{
		// TODO: Log chargeback resolution
	}

	/**
	 * Handle subscription lifecycle events.
	 *
	 * @param	string	$eventType	ON_SUBSCRIPTION_ACTIVATED / ON_SUBSCRIPTION_RENEWED / ON_SUBSCRIPTION_CANCELED
	 * @param	array	$body		Subscription data
	 * @param	string	$eventId	PayNow event ID
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleSubscriptionEvent( $eventType, array $body, $eventId, $gateway, array $settings )
	{
		// TODO: Handle subscription activation, renewal, cancellation
	}

	/**
	 * Build a normalized settlement snapshot from PayNow order data.
	 *
	 * @param	array						$body			Order data from webhook body
	 * @param	\IPS\nexus\Transaction		$transaction	IPS transaction
	 * @return	array
	 */
	protected function buildPaynowSnapshot( array $body, $transaction )
	{
		$orderId        = isset( $body['id'] ) ? (string) $body['id'] : '';
		$prettyId       = isset( $body['pretty_id'] ) ? (string) $body['pretty_id'] : '';
		$currency       = isset( $body['currency'] ) ? \mb_strtoupper( (string) $body['currency'] ) : '';
		$subtotalMinor  = isset( $body['subtotal_amount'] ) ? (int) $body['subtotal_amount'] : 0;
		$taxMinor       = isset( $body['tax_amount'] ) ? (int) $body['tax_amount'] : 0;
		$discountMinor  = isset( $body['discount_amount'] ) ? (int) $body['discount_amount'] : 0;
		$totalMinor     = isset( $body['total_amount'] ) ? (int) $body['total_amount'] : 0;

		$subtotalDisplay = isset( $body['subtotal_amount_str'] ) ? (string) $body['subtotal_amount_str'] : '';
		$taxDisplay      = isset( $body['tax_amount_str'] ) ? (string) $body['tax_amount_str'] : '';
		$discountDisplay = isset( $body['discount_amount_str'] ) ? (string) $body['discount_amount_str'] : '';
		$totalDisplay    = isset( $body['total_amount_str'] ) ? (string) $body['total_amount_str'] : '';

		/* Compute IPS invoice total in minor units for comparison */
		$ipsInvoiceTotal = 0;
		try
		{
			$ipsMoney = $transaction->amount;
			$decimals = \IPS\nexus\Money::numberOfDecimalsForCurrency( $ipsMoney->currency );
			$multiplier = new \IPS\Math\Number( '1' . \str_repeat( '0', $decimals ) );
			$ipsInvoiceTotal = (int) (string) $ipsMoney->amount->multiply( $multiplier );
		}
		catch ( \Exception $e ) {}

		$hasMismatch = ( $totalMinor !== $ipsInvoiceTotal );
		$taxExplained = FALSE;
		if ( $hasMismatch AND $taxMinor > 0 )
		{
			$totalMinusTax = $totalMinor - $taxMinor;
			$taxExplained = ( $totalMinusTax === $ipsInvoiceTotal );
		}

		return array(
			'captured_at'                    => \time(),
			'captured_at_iso'                => \gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'paynow_order_id'                => $orderId,
			'paynow_pretty_id'               => $prettyId,
			'currency'                       => $currency,
			'subtotal_minor'                 => $subtotalMinor,
			'subtotal_display'               => $subtotalDisplay,
			'tax_minor'                      => $taxMinor,
			'tax_display'                    => $taxDisplay,
			'discount_minor'                 => $discountMinor,
			'discount_display'               => $discountDisplay,
			'total_minor'                    => $totalMinor,
			'total_display'                  => $totalDisplay,
			'billing_name'                   => isset( $body['billing_name'] ) ? (string) $body['billing_name'] : '',
			'billing_email'                  => isset( $body['billing_email'] ) ? (string) $body['billing_email'] : '',
			'billing_country'                => isset( $body['billing_country'] ) ? (string) $body['billing_country'] : '',
			'completed_at'                   => isset( $body['completed_at'] ) ? (string) $body['completed_at'] : '',
			'ips_invoice_total'              => $ipsInvoiceTotal,
			'has_total_mismatch'             => $hasMismatch,
			'total_difference_tax_explained' => $taxExplained,
		);
	}

	/**
	 * Log a webhook forensics entry.
	 *
	 * @param	string	$failureReason
	 * @param	string	$eventType
	 * @param	string	$eventId
	 * @param	string	$signature
	 * @param	string	$ipAddress
	 * @param	int		$httpStatus
	 * @param	string	$payload
	 * @return	void
	 */
	protected function logForensic( $failureReason, $eventType, $eventId, $signature, $ipAddress, $httpStatus, $payload )
	{
		try
		{
			\IPS\Db::i()->insert( 'pnc_webhook_forensics', array(
				'failure_reason'  => (string) $failureReason,
				'event_type'      => (string) $eventType,
				'event_id'        => !empty( $eventId ) ? (string) $eventId : NULL,
				'ip_address'      => (string) $ipAddress,
				'http_status'     => (int) $httpStatus,
				'payload_snippet' => \mb_substr( (string) $payload, 0, 2000 ),
				'created_at'      => \time(),
			) );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_forensics' );
		}
	}
}
