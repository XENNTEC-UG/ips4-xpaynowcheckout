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
 * Lightweight polling task that checks payment integrity metrics
 * and sends/clears ACP admin notifications via the PaymentIntegrity extension.
 *
 * Runs every 5 minutes. Only local DB queries — no external API calls.
 */
class _pncIntegrityMonitor extends \IPS\Task
{
	/**
	 * @brief	Forensics retention: 90 days
	 */
	const FORENSICS_RETENTION_DAYS = 90;

	/**
	 * Execute.
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		try
		{
			\IPS\xpaynowcheckout\extensions\core\AdminNotifications\PaymentIntegrity::runChecksAndSendNotifications();
		}
		catch ( \Throwable $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_integrity_monitor' );
		}

		/* Prune old forensics entries (once daily via last-cleaned check) */
		try
		{
			$lastCleaned = isset( \IPS\Data\Store::i()->pnc_forensics_last_cleaned ) ? (int) \IPS\Data\Store::i()->pnc_forensics_last_cleaned : 0;
			if ( \time() - $lastCleaned > 86400 )
			{
				$cutoff = \time() - ( static::FORENSICS_RETENTION_DAYS * 86400 );
				\IPS\Db::i()->delete( 'pnc_webhook_forensics', array( 'created_at<?', $cutoff ) );
				\IPS\Data\Store::i()->pnc_forensics_last_cleaned = \time();
			}
		}
		catch ( \Throwable $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_forensics_cleanup' );
		}

		return NULL;
	}
}
