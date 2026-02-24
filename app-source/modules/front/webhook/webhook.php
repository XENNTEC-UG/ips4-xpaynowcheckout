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
			$this->logForensic( 'invalid_gateway', '', '', '', 0, $rawPayload );
			\IPS\Output::i()->json( array( 'error' => 'gateway_not_found' ), 400 );
			return;
		}

		/* Validate signature */
		if ( empty( $settings['webhook_secret'] ) )
		{
			$this->logForensic( 'missing_secret', '', '', '', 0, $rawPayload );
			\IPS\Output::i()->json( array( 'error' => 'webhook_not_configured' ), 500 );
			return;
		}

		if ( !\IPS\xpaynowcheckout\XPaynowCheckout::verifyWebhookSignature( $rawPayload, $signature, $timestamp, $settings['webhook_secret'] ) )
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
			$this->logForensic( $failureReason, '', $signature, \IPS\Request::i()->ipAddress(), 0, $rawPayload );
			\IPS\Output::i()->json( array( 'error' => 'invalid_signature' ), 401 );
			return;
		}

		/* Parse payload */
		$payload = json_decode( $rawPayload, TRUE );
		if ( !\is_array( $payload ) )
		{
			$this->logForensic( 'invalid_payload', '', $signature, \IPS\Request::i()->ipAddress(), 0, $rawPayload );
			\IPS\Output::i()->json( array( 'error' => 'invalid_payload' ), 400 );
			return;
		}

		$eventType = isset( $payload['event'] ) ? (string) $payload['event'] : '';
		$eventId = isset( $payload['id'] ) ? (string) $payload['id'] : '';

		try
		{
			$this->processEvent( $eventType, $payload, $gateway, $settings );
			\IPS\Output::i()->json( array( 'ok' => TRUE ), 200 );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_webhook' );
			\IPS\Output::i()->json( array( 'error' => 'processing_error' ), 500 );
		}
	}

	/**
	 * Process a PayNow webhook event.
	 *
	 * @param	string							$eventType	Event type string
	 * @param	array							$payload	Full event payload
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway	Gateway instance
	 * @param	array							$settings	Gateway settings
	 * @return	void
	 */
	protected function processEvent( $eventType, array $payload, $gateway, array $settings )
	{
		switch ( $eventType )
		{
			case 'OnOrderCompleted':
				$this->handleOrderCompleted( $payload, $gateway, $settings );
				break;

			case 'OnRefund':
				$this->handleRefund( $payload, $gateway, $settings );
				break;

			case 'OnChargeback':
				$this->handleChargeback( $payload, $gateway, $settings );
				break;

			case 'OnChargebackClosed':
				$this->handleChargebackClosed( $payload, $gateway, $settings );
				break;

			case 'OnSubscriptionActivated':
			case 'OnSubscriptionRenewed':
			case 'OnSubscriptionCanceled':
				$this->handleSubscriptionEvent( $eventType, $payload, $gateway, $settings );
				break;

			default:
				\IPS\Log::log( 'Unhandled PayNow webhook event: ' . $eventType, 'xpaynowcheckout_webhook' );
				break;
		}
	}

	/**
	 * Handle OnOrderCompleted — approve the pending transaction.
	 *
	 * @param	array	$payload
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleOrderCompleted( array $payload, $gateway, array $settings )
	{
		// TODO: Extract order_id, payment details from payload
		// TODO: Find matching IPS transaction by metadata.ips_transaction_id
		// TODO: Build settlement snapshot (subtotal, tax, total, currency)
		// TODO: Store snapshot in t_extra
		// TODO: Approve transaction → triggers markPaid on invoice
	}

	/**
	 * Handle OnRefund — record refund on the IPS transaction.
	 *
	 * @param	array	$payload
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleRefund( array $payload, $gateway, array $settings )
	{
		// TODO: Find matching IPS transaction
		// TODO: Record refund status
	}

	/**
	 * Handle OnChargeback — ban member if configured.
	 *
	 * @param	array	$payload
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleChargeback( array $payload, $gateway, array $settings )
	{
		// TODO: Find matching IPS transaction
		// TODO: If chargeback_ban enabled, ban the member
		// TODO: Update transaction status
	}

	/**
	 * Handle OnChargebackClosed.
	 *
	 * @param	array	$payload
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleChargebackClosed( array $payload, $gateway, array $settings )
	{
		// TODO: Log chargeback resolution
	}

	/**
	 * Handle subscription lifecycle events.
	 *
	 * @param	string	$eventType
	 * @param	array	$payload
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway
	 * @param	array	$settings
	 * @return	void
	 */
	protected function handleSubscriptionEvent( $eventType, array $payload, $gateway, array $settings )
	{
		// TODO: Handle subscription activation, renewal, cancellation
	}

	/**
	 * Log a webhook forensics entry.
	 *
	 * @param	string	$failureReason
	 * @param	string	$eventType
	 * @param	string	$signature
	 * @param	string	$ipAddress
	 * @param	int		$httpStatus
	 * @param	string	$payload
	 * @return	void
	 */
	protected function logForensic( $failureReason, $eventType, $signature, $ipAddress, $httpStatus, $payload )
	{
		try
		{
			\IPS\Db::i()->insert( 'pnc_webhook_forensics', array(
				'failure_reason'  => (string) $failureReason,
				'event_type'      => (string) $eventType,
				'event_id'        => NULL,
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
