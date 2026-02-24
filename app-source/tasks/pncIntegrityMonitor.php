<?php
/**
 * @brief		PayNow Integrity Monitor Task
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
 * Integrity Monitor Task — checks alert conditions and prunes old forensics.
 *
 * Runs every 5 minutes.
 */
class _pncIntegrityMonitor extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		$stats = \IPS\xpaynowcheckout\XPaynowCheckout::collectAlertStats();

		/* Check alert conditions and raise AdminNotifications */
		if ( $stats['webhook_error_count_24h'] > 0 )
		{
			$this->raiseAlert( 'webhook_errors', $stats );
		}

		if ( !$stats['replay_recent_run'] )
		{
			$this->raiseAlert( 'replay_stale', $stats );
		}

		if ( $stats['mismatch_count_30d'] > 0 )
		{
			$this->raiseAlert( 'mismatches', $stats );
		}

		/* Prune forensics older than 90 days */
		$this->pruneForensics();

		return NULL;
	}

	/**
	 * Raise an AdminNotification alert.
	 *
	 * @param	string	$alertType
	 * @param	array	$stats
	 * @return	void
	 */
	protected function raiseAlert( $alertType, array $stats )
	{
		// TODO: Send AdminNotification via PaymentIntegrity extension
	}

	/**
	 * Prune forensics entries older than 90 days.
	 *
	 * @return	void
	 */
	protected function pruneForensics()
	{
		$cutoff = \time() - ( 90 * 86400 );

		try
		{
			\IPS\Db::i()->delete( 'pnc_webhook_forensics', array( 'created_at<?', $cutoff ) );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_forensics' );
		}
	}
}
