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
		$eventBody = isset( $payload['body'] ) AND \is_array( $payload['body'] ) ? $payload['body'] : array();

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
		// TODO: Extract order_id from $body['id'], payment details from $body
		// TODO: Find matching IPS transaction via $body['checkout']['metadata']['ips_transaction_id']
		// TODO: Build settlement snapshot (subtotal_amount, tax_amount, total_amount, discount_amount, currency)
		// TODO: Store snapshot in t_extra
		// TODO: Approve transaction → triggers markPaid on invoice
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
