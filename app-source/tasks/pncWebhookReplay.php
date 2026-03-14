<?php
/**
 * @brief		PayNow Webhook Replay Task
 * @author      https://xenntec.com/
 */

namespace IPS\xpaynowcheckout\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Webhook Replay Task — fetches webhook delivery history from PayNow and replays failed events.
 *
 * Runs every 15 minutes.
 * PayNow creates one webhook subscription per event type, each with its own ID.
 * This task lists all subscriptions, fetches delivery history per webhook,
 * filters failed deliveries, deduplicates by event_id, and forwards them
 * to the local webhook endpoint with valid PayNow signature headers.
 */
class _pncWebhookReplay extends \IPS\Task
{
	/**
	 * @brief Store key for replay state.
	 */
	const REPLAY_STATE_STORE_KEY = 'xpaynowcheckout_webhook_replay_state';

	/**
	 * @brief Replay defaults / hard limits.
	 */
	const DEFAULT_LOOKBACK_SECONDS = 3600;
	const REPLAY_OVERLAP_SECONDS = 300;
	const MAX_EVENTS_PER_RUN = 100;
	const MAX_RUNTIME_SECONDS = 120;

	/**
	 * Execute.
	 *
	 * @param	bool	$dryRun	If TRUE, fetch and filter replay candidates without forwarding or state mutation.
	 * @return	mixed
	 * @throws	\IPS\Task\Exception
	 */
	public function execute( $dryRun = FALSE )
	{
		$settings = $this->loadGatewaySettings();
		if ( $settings === NULL )
		{
			return $dryRun ? array( 'count' => 0, 'events' => array() ) : NULL;
		}

		if ( empty( $settings['api_key'] ) || empty( $settings['store_id'] ) )
		{
			return $dryRun ? array( 'count' => 0, 'events' => array() ) : NULL;
		}

		$lookback = isset( $settings['replay_lookback'] ) ? (int) $settings['replay_lookback'] : static::DEFAULT_LOOKBACK_SECONDS;
		$overlap = isset( $settings['replay_overlap'] ) ? (int) $settings['replay_overlap'] : static::REPLAY_OVERLAP_SECONDS;
		$maxEvents = isset( $settings['replay_max_events'] ) ? (int) $settings['replay_max_events'] : static::MAX_EVENTS_PER_RUN;

		$lookback = \max( 300, \min( 86400, $lookback ) );
		$overlap = \max( 60, \min( 1800, $overlap ) );
		$maxEvents = \max( 10, \min( static::MAX_EVENTS_PER_RUN, $maxEvents ) );

		$state = $this->loadReplayState();
		$windowStart = $this->resolveReplayWindowStart( $state, $lookback, $overlap );
		$windowEnd = \time();

		try
		{
			$deliveries = $this->fetchWebhookDeliveries( $settings, $windowStart, $windowEnd );
		}
		catch ( \IPS\Task\Exception $e )
		{
			if ( !$dryRun )
			{
				$this->saveReplayState( array(
					'last_run_at' => \time(),
					'last_event_created' => isset( $state['last_event_created'] ) ? $state['last_event_created'] : $windowStart,
					'last_event_id' => isset( $state['last_event_id'] ) ? $state['last_event_id'] : NULL,
					'last_replayed_count' => 0,
				) );
			}

			throw $e;
		}

		$candidates = $this->extractReplayCandidates( $deliveries, $maxEvents );
		if ( !\count( $candidates ) )
		{
			if ( !$dryRun )
			{
				$this->saveReplayState( array(
					'last_run_at' => \time(),
					'last_event_created' => \max( $windowStart, \time() ),
					'last_event_id' => isset( $state['last_event_id'] ) ? $state['last_event_id'] : NULL,
					'last_replayed_count' => 0,
				) );
			}

			return $dryRun ? array( 'count' => 0, 'events' => array() ) : NULL;
		}

		$replayed = 0;
		$maxCreated = (int) $windowStart;
		$maxEventId = NULL;
		$dryRunEvents = array();

		/* Resolve any signing secret for replay headers */
		$signingSecret = $this->resolveSigningSecret( $settings );

		foreach ( $candidates as $candidate )
		{
			$eventId = (string) $candidate['event_id'];
			$eventType = (string) $candidate['event_type'];
			$payload = (string) $candidate['payload'];
			$eventCreatedTs = $this->safeUnixTimestamp( isset( $candidate['created_at'] ) ? $candidate['created_at'] : NULL );

			if ( $dryRun )
			{
				$dryRunEvents[] = array(
					'id' => $eventId,
					'type' => $eventType,
					'created' => $eventCreatedTs,
					'delivery_id' => (string) $candidate['delivery_id'],
					'http_code' => $candidate['http_code'],
				);
			}
			else
			{
				$this->forwardEventToWebhook( $payload, $signingSecret );
			}

			$replayed++;
			if ( $eventCreatedTs >= $maxCreated )
			{
				$maxCreated = $eventCreatedTs;
				$maxEventId = $eventId;
			}
		}

		if ( $dryRun )
		{
			return array(
				'count' => $replayed,
				'events' => $dryRunEvents,
			);
		}

		$this->saveReplayState( array(
			'last_run_at' => \time(),
			'last_event_created' => \max( $maxCreated, (int) $windowStart ),
			'last_event_id' => $maxEventId,
			'last_replayed_count' => $replayed,
		) );

		return $replayed ? "Replayed {$replayed} PayNow webhook event(s)." : NULL;
	}

	/**
	 * Cleanup.
	 *
	 * @return	void
	 */
	public function cleanup()
	{
	}

	/**
	 * Fetch webhook deliveries from PayNow API.
	 *
	 * PayNow uses one webhook subscription per event type. This method:
	 * 1. Lists all webhook subscriptions for the store
	 * 2. Fetches delivery history per webhook (filtering for failures)
	 * 3. Merges all deliveries into a single array
	 *
	 * @param	array	$settings		Gateway settings
	 * @param	int		$windowStart	Start of replay window (unix timestamp)
	 * @param	int		$windowEnd		End of replay window (unix timestamp)
	 * @return	array
	 * @throws	\IPS\Task\Exception
	 */
	protected function fetchWebhookDeliveries( array $settings, $windowStart, $windowEnd )
	{
		$storeId = (string) $settings['store_id'];
		$startedAt = \time();

		/* Step 1: List all webhook subscriptions */
		try
		{
			$webhooks = \IPS\xpaynowcheckout\XPaynowCheckout::apiRequest( 'get', '/stores/' . $storeId . '/webhooks', $settings );
		}
		catch ( \Exception $e )
		{
			throw new \IPS\Task\Exception( $this, 'Unable to list PayNow webhooks: ' . $e->getMessage() );
		}

		if ( !\is_array( $webhooks ) )
		{
			return array();
		}

		/* Step 2: Fetch delivery history per webhook */
		$allDeliveries = array();
		foreach ( $webhooks as $wh )
		{
			if ( ( \time() - $startedAt ) >= static::MAX_RUNTIME_SECONDS )
			{
				break;
			}

			if ( !isset( $wh['id'] ) || !\is_string( $wh['id'] ) )
			{
				continue;
			}

			$webhookId = (string) $wh['id'];

			try
			{
				$history = \IPS\xpaynowcheckout\XPaynowCheckout::apiRequest(
					'get',
					'/stores/' . $storeId . '/webhooks/' . $webhookId . '/history',
					$settings
				);
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( 'Replay: failed to fetch history for webhook ' . $webhookId . ': ' . $e->getMessage(), 'xpaynowcheckout_replay' );
				continue;
			}

			if ( !\is_array( $history ) )
			{
				continue;
			}

			foreach ( $history as $delivery )
			{
				if ( !\is_array( $delivery ) )
				{
					continue;
				}

				/* Filter: only failed deliveries */
				$succeeded = !empty( $delivery['succeeded'] );
				if ( $succeeded )
				{
					continue;
				}

				/* Filter: within time window */
				$deliveryTs = $this->safeUnixTimestamp( isset( $delivery['created_at'] ) ? $delivery['created_at'] : NULL );
				if ( $deliveryTs > 0 && ( $deliveryTs < $windowStart || $deliveryTs > $windowEnd ) )
				{
					continue;
				}

				$allDeliveries[] = $delivery;
			}
		}

		return $allDeliveries;
	}

	/**
	 * Extract replayable candidates from webhook deliveries.
	 *
	 * Filters by required event types, deduplicates by event_id (keeps latest),
	 * and sorts chronologically.
	 *
	 * @param	array	$deliveries	Raw delivery records
	 * @param	int		$maxEvents	Maximum candidates to return
	 * @return	array
	 */
	protected function extractReplayCandidates( array $deliveries, $maxEvents )
	{
		$requiredTypes = \IPS\xpaynowcheckout\XPaynowCheckout::REQUIRED_WEBHOOK_EVENTS;
		$byEventId = array();

		foreach ( $deliveries as $delivery )
		{
			$eventId = isset( $delivery['event_id'] ) ? (string) $delivery['event_id'] : '';
			$eventType = isset( $delivery['event_type'] ) ? (string) $delivery['event_type'] : '';
			$payload = isset( $delivery['payload'] ) ? (string) $delivery['payload'] : '';

			/* Filter: only required event types (PascalCase from PayNow delivery history) */
			if ( !\in_array( $eventType, $requiredTypes, TRUE ) )
			{
				continue;
			}

			if ( empty( $eventId ) || empty( $payload ) )
			{
				continue;
			}

			$created = $this->safeUnixTimestamp( isset( $delivery['created_at'] ) ? $delivery['created_at'] : NULL );
			$httpCode = isset( $delivery['http_status'] ) && \is_numeric( $delivery['http_status'] ) ? (int) $delivery['http_status'] : NULL;

			$candidate = array(
				'delivery_id' => isset( $delivery['id'] ) ? (string) $delivery['id'] : '',
				'event_id' => $eventId,
				'event_type' => $eventType,
				'payload' => $payload,
				'created_at' => isset( $delivery['created_at'] ) ? $delivery['created_at'] : NULL,
				'replay_sort_ts' => $created,
				'http_code' => $httpCode,
			);

			/* Deduplicate: keep latest delivery per event_id */
			if ( !isset( $byEventId[ $eventId ] ) || $candidate['replay_sort_ts'] > $byEventId[ $eventId ]['replay_sort_ts'] )
			{
				$byEventId[ $eventId ] = $candidate;
			}
		}

		$candidates = \array_values( $byEventId );
		\usort( $candidates, function( $a, $b ) {
			$aTs = isset( $a['replay_sort_ts'] ) ? (int) $a['replay_sort_ts'] : 0;
			$bTs = isset( $b['replay_sort_ts'] ) ? (int) $b['replay_sort_ts'] : 0;
			if ( $aTs === $bTs )
			{
				return \strcmp( (string) $a['delivery_id'], (string) $b['delivery_id'] );
			}
			return $aTs < $bTs ? -1 : 1;
		} );

		if ( \count( $candidates ) > $maxEvents )
		{
			$candidates = \array_slice( $candidates, 0, $maxEvents );
		}

		return $candidates;
	}

	/**
	 * Forward one replay payload to local webhook endpoint with valid PayNow signature headers.
	 *
	 * PayNow uses base64 HMAC-SHA256 with PayNow-Signature and PayNow-Timestamp headers.
	 * Timestamp is in milliseconds.
	 *
	 * @param	string	$payload		Raw JSON payload
	 * @param	string	$signingSecret	Webhook signing secret
	 * @return	void
	 * @throws	\IPS\Task\Exception
	 */
	protected function forwardEventToWebhook( $payload, $signingSecret )
	{
		if ( !\is_string( $payload ) || $payload === '' )
		{
			throw new \IPS\Task\Exception( $this, 'Unable to replay empty webhook payload.' );
		}

		/* PayNow timestamps are in milliseconds */
		$timestampMs = (string) ( \time() * 1000 );

		/* Build signature: base64(HMAC-SHA256(timestamp_ms + '.' + payload, secret)) */
		$signedPayload = $timestampMs . '.' . $payload;
		$signature = \base64_encode( \hash_hmac( 'sha256', $signedPayload, $signingSecret, TRUE ) );

		$headers = array(
			'Content-Type' => 'application/json',
			'PayNow-Signature' => $signature,
			'PayNow-Timestamp' => $timestampMs,
		);

		$webhookUrl = \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=webhook&controller=webhook', 'front' );
		$allowInsecureTls = ( \defined( '\IPS\IN_DEV' ) && \IPS\IN_DEV );

		try
		{
			$request = \IPS\Http\Url::external( (string) $webhookUrl )
				->request( 20 )
				->setHeaders( $headers )
				->sslCheck( !$allowInsecureTls );
			$response = $request->post( $payload );
		}
		catch ( \Exception $e )
		{
			throw new \IPS\Task\Exception( $this, 'Replay delivery failed: ' . $e->getMessage() );
		}

		$statusCode = (int) $response->httpResponseCode;
		if ( $statusCode !== 200 )
		{
			throw new \IPS\Task\Exception( $this, 'Replay delivery returned HTTP ' . $statusCode . ' with body: ' . (string) $response );
		}
	}

	/**
	 * Resolve a signing secret from gateway settings.
	 *
	 * Uses the first available secret from webhook_secrets or webhook_secret.
	 *
	 * @param	array	$settings	Gateway settings
	 * @return	string
	 */
	protected function resolveSigningSecret( array $settings )
	{
		$secrets = isset( $settings['webhook_secrets'] ) ? (array) $settings['webhook_secrets'] : array();
		if ( !empty( $settings['webhook_secret'] ) )
		{
			$secrets[] = (string) $settings['webhook_secret'];
		}

		$secrets = \array_filter( $secrets );

		return !empty( $secrets ) ? \reset( $secrets ) : '';
	}

	/**
	 * Resolve gateway settings for active XPaynowCheckout gateway.
	 *
	 * @return	array|NULL
	 */
	protected function loadGatewaySettings()
	{
		foreach ( \IPS\nexus\Gateway::roots() as $gateway )
		{
			if ( $gateway instanceof \IPS\xpaynowcheckout\XPaynowCheckout )
			{
				$settings = \json_decode( $gateway->settings, TRUE );
				return \is_array( $settings ) ? $settings : NULL;
			}
		}

		return NULL;
	}

	/**
	 * Resolve replay window start.
	 *
	 * @param	array	$state		Previous replay state
	 * @param	int		$lookback	Lookback seconds
	 * @param	int		$overlap	Overlap seconds
	 * @return	int
	 */
	protected function resolveReplayWindowStart( array $state, $lookback = 3600, $overlap = 300 )
	{
		if ( isset( $state['last_event_created'] ) && \is_numeric( $state['last_event_created'] ) )
		{
			return \max( 0, (int) $state['last_event_created'] - $overlap );
		}

		return \max( 0, \time() - $lookback );
	}

	/**
	 * Parse arbitrary timestamp value into unix epoch.
	 *
	 * @param	mixed	$value
	 * @return	int
	 */
	protected function safeUnixTimestamp( $value )
	{
		if ( \is_numeric( $value ) )
		{
			$intVal = (int) $value;
			/* PayNow may use millisecond timestamps — normalize */
			if ( $intVal > 9999999999 )
			{
				return (int) ( $intVal / 1000 );
			}
			return $intVal;
		}

		if ( \is_string( $value ) && $value !== '' )
		{
			$ts = \strtotime( $value );
			if ( $ts !== FALSE )
			{
				return (int) $ts;
			}
		}

		return 0;
	}

	/**
	 * Load replay state from datastore.
	 *
	 * @return	array
	 */
	protected function loadReplayState()
	{
		if ( isset( \IPS\Data\Store::i()->{static::REPLAY_STATE_STORE_KEY} ) && \is_array( \IPS\Data\Store::i()->{static::REPLAY_STATE_STORE_KEY} ) )
		{
			return \IPS\Data\Store::i()->{static::REPLAY_STATE_STORE_KEY};
		}

		return array();
	}

	/**
	 * Save replay state to datastore.
	 *
	 * @param	array	$state
	 * @return	void
	 */
	protected function saveReplayState( array $state )
	{
		\IPS\Data\Store::i()->{static::REPLAY_STATE_STORE_KEY} = $state;
	}
}
