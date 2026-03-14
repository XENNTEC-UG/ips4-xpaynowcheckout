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
	 * Resolve an IPS transaction from webhook body metadata.
	 *
	 * Three-level fallback:
	 * 1. $body['metadata']['ips_transaction_id']
	 * 2. $body['checkout']['metadata']['ips_transaction_id']
	 * 3. DB lookup by $body['order_id'] or $body['checkout_id'] as gw_id
	 *
	 * @param	array									$body		Webhook event body
	 * @param	\IPS\xpaynowcheckout\XPaynowCheckout	$gateway	Gateway instance
	 * @return	\IPS\nexus\Transaction
	 * @throws	\OutOfRangeException	If transaction cannot be resolved
	 */
	protected function resolveTransactionFromWebhook( array $body, $gateway )
	{
		$transactionId = NULL;

		/* Level 1: direct metadata */
		if ( isset( $body['metadata']['ips_transaction_id'] ) )
		{
			$transactionId = (int) $body['metadata']['ips_transaction_id'];
		}
		/* Level 2: nested checkout metadata */
		elseif ( isset( $body['checkout']['metadata']['ips_transaction_id'] ) )
		{
			$transactionId = (int) $body['checkout']['metadata']['ips_transaction_id'];
		}
		/* Level 3: DB lookup by order_id or checkout_id stored as gw_id */
		else
		{
			$gwIdCandidates = array();
			if ( isset( $body['order_id'] ) AND \is_string( $body['order_id'] ) )
			{
				$gwIdCandidates[] = $body['order_id'];
			}
			if ( isset( $body['checkout_id'] ) AND \is_string( $body['checkout_id'] ) )
			{
				$gwIdCandidates[] = $body['checkout_id'];
			}
			if ( isset( $body['id'] ) AND \is_string( $body['id'] ) )
			{
				$gwIdCandidates[] = $body['id'];
			}

			foreach ( $gwIdCandidates as $candidate )
			{
				try
				{
					$transactionId = (int) \IPS\Db::i()->select( 't_id', 'nexus_transactions', array( 't_gw_id=? AND t_method=?', $candidate, $gateway->id ) )->first();
					break;
				}
				catch ( \UnderflowException $e ) {}
			}
		}

		if ( !$transactionId )
		{
			throw new \OutOfRangeException( 'No transaction resolved from webhook body' );
		}

		return \IPS\nexus\Transaction::load( $transactionId );
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
		/* Resolve IPS transaction */
		try
		{
			$transaction = $this->resolveTransactionFromWebhook( $body, $gateway );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Log::log( 'ON_ORDER_COMPLETED: ' . $e->getMessage() . '. Order: ' . ( isset( $body['id'] ) ? $body['id'] : 'unknown' ), 'xpaynowcheckout_webhook' );
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
		/* Resolve transaction */
		try
		{
			$transaction = $this->resolveTransactionFromWebhook( $body, $gateway );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Log::log( 'ON_REFUND: ' . $e->getMessage() . '. Event: ' . $eventId, 'xpaynowcheckout_webhook' );
			return;
		}

		/* Idempotency — skip if already refunded */
		if ( $transaction->status === \IPS\nexus\Transaction::STATUS_REFUNDED )
		{
			return;
		}

		/* Only mutate on terminal refund statuses */
		$refundStatus = isset( $body['status'] ) ? (string) $body['status'] : '';
		if ( $refundStatus !== 'completed' AND $refundStatus !== 'approved' )
		{
			\IPS\Log::log( 'ON_REFUND: Non-terminal status "' . $refundStatus . '" for transaction ' . $transaction->id . '. Skipping.', 'xpaynowcheckout_webhook' );
			return;
		}

		/* Store refund metadata */
		$extra = $transaction->extra;
		if ( !\is_array( $extra ) )
		{
			$extra = array();
		}
		$extra['xpaynowcheckout_refund'] = array(
			'refund_id'      => isset( $body['id'] ) ? (string) $body['id'] : '',
			'payment_id'     => isset( $body['payment_id'] ) ? (string) $body['payment_id'] : '',
			'order_id'       => isset( $body['order_id'] ) ? (string) $body['order_id'] : '',
			'amount'         => isset( $body['amount'] ) ? (int) $body['amount'] : 0,
			'amount_str'     => isset( $body['amount_str'] ) ? (string) $body['amount_str'] : '',
			'status'         => $refundStatus,
			'failure_reason' => isset( $body['failure_reason'] ) ? (string) $body['failure_reason'] : '',
			'event_id'       => $eventId,
			'captured_at'    => \time(),
		);
		$transaction->extra = $extra;

		/* PayNow only supports full refunds */
		$transaction->status = \IPS\nexus\Transaction::STATUS_REFUNDED;
		$transaction->save();
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
		/* Resolve transaction */
		try
		{
			$transaction = $this->resolveTransactionFromWebhook( $body, $gateway );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Log::log( 'ON_CHARGEBACK: ' . $e->getMessage() . '. Event: ' . $eventId, 'xpaynowcheckout_webhook' );
			return;
		}

		/* Idempotency — skip if already disputed */
		if ( $transaction->status === \IPS\nexus\Transaction::STATUS_DISPUTED )
		{
			return;
		}

		/* Store chargeback metadata */
		$extra = $transaction->extra;
		if ( !\is_array( $extra ) )
		{
			$extra = array();
		}
		$extra['xpaynowcheckout_chargeback'] = array(
			'order_id'    => isset( $body['order_id'] ) ? (string) $body['order_id'] : ( isset( $body['id'] ) ? (string) $body['id'] : '' ),
			'amount'      => isset( $body['amount'] ) ? (int) $body['amount'] : 0,
			'amount_str'  => isset( $body['amount_str'] ) ? (string) $body['amount_str'] : '',
			'status'      => isset( $body['status'] ) ? (string) $body['status'] : 'opened',
			'reason'      => isset( $body['reason'] ) ? (string) $body['reason'] : '',
			'event_id'    => $eventId,
			'created_at'  => \time(),
		);
		$transaction->extra = $extra;
		$transaction->status = \IPS\nexus\Transaction::STATUS_DISPUTED;
		$transaction->save();

		/* Ban member if chargeback_ban is enabled (default TRUE) */
		$chargebackBan = isset( $settings['chargeback_ban'] ) ? (bool) $settings['chargeback_ban'] : TRUE;
		$member = $transaction->member;

		if ( $chargebackBan AND $member AND $member->member_id )
		{
			$member->temp_ban = -1;
			$member->save();
			$member->logHistory( 'core', 'warning', array(
				'type'   => 'dispute_ban',
				'reason' => 'PayNow chargeback on transaction ' . $transaction->id,
			) );
		}

		/* Revoke benefits — mark invoice unpaid/canceled */
		try
		{
			$transaction->invoice->markUnpaid( \IPS\nexus\Invoice::STATUS_CANCELED );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_chargeback' );
		}

		/* Admin notification */
		try
		{
			\IPS\core\AdminNotification::send( 'nexus', 'Transaction', \IPS\nexus\Transaction::STATUS_DISPUTED, TRUE, $transaction );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_chargeback' );
		}
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
		/* Resolve transaction */
		try
		{
			$transaction = $this->resolveTransactionFromWebhook( $body, $gateway );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Log::log( 'ON_CHARGEBACK_CLOSED: ' . $e->getMessage() . '. Event: ' . $eventId, 'xpaynowcheckout_webhook' );
			return;
		}

		/* Extract resolution from body */
		$resolution = isset( $body['status'] ) ? (string) $body['status'] : '';

		/* Update chargeback metadata with closure info */
		$extra = $transaction->extra;
		if ( !\is_array( $extra ) )
		{
			$extra = array();
		}
		if ( !isset( $extra['xpaynowcheckout_chargeback'] ) OR !\is_array( $extra['xpaynowcheckout_chargeback'] ) )
		{
			$extra['xpaynowcheckout_chargeback'] = array();
		}
		$extra['xpaynowcheckout_chargeback']['resolution']     = $resolution;
		$extra['xpaynowcheckout_chargeback']['closed_at']      = \time();
		$extra['xpaynowcheckout_chargeback']['close_event_id'] = $eventId;
		$transaction->extra = $extra;

		/* Update transaction status based on resolution */
		if ( $resolution === 'won' )
		{
			$transaction->status = \IPS\nexus\Transaction::STATUS_PAID;
			$transaction->save();

			/* Re-mark invoice as paid if balance is zero */
			try
			{
				$invoice = $transaction->invoice;
				if ( $invoice->amountToPay()->amount->isZero() )
				{
					$invoice->markPaid();
				}
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( $e, 'xpaynowcheckout_chargeback_closed' );
			}
		}
		elseif ( $resolution === 'lost' )
		{
			$transaction->status = \IPS\nexus\Transaction::STATUS_REFUNDED;
			$transaction->save();
		}
		else
		{
			/* Unknown resolution — persist metadata only, don't change status */
			$transaction->save();
		}
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
			$ipsInvoiceTotal = \IPS\xpaynowcheckout\XPaynowCheckout::moneyToMinorUnit( $transaction->amount );
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
