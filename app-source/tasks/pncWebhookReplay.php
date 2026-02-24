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
 * Uses GET /v1/stores/{storeId}/webhooks/{webhookId}/history to find failed deliveries.
 */
class _pncWebhookReplay extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		// TODO: Load gateway settings
		// TODO: Fetch webhook history from PayNow API (state=failed)
		// TODO: Filter by REQUIRED_WEBHOOK_EVENTS
		// TODO: Deduplicate by event_id
		// TODO: Use POST /v1/stores/{storeId}/webhooks/resend to retry
		// TODO: Save replay state to datastore

		$this->saveReplayState();

		return NULL;
	}

	/**
	 * Save replay execution state to datastore.
	 *
	 * @return	void
	 */
	protected function saveReplayState()
	{
		\IPS\Data\Store::i()->xpaynowcheckout_webhook_replay_state = array(
			'last_run_at' => \time(),
		);
	}
}
