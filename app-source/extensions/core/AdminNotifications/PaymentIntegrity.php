<?php
/**
 * @brief		PayNow Payment Integrity Admin Notification
 * @author      https://xenntec.com/
 */

namespace IPS\xpaynowcheckout\extensions\core\AdminNotifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Admin Notification for PayNow Payment Integrity alerts.
 */
class _PaymentIntegrity extends \IPS\core\AdminNotification
{
	/**
	 * @brief	Identifier for what to group this notification type with on the settings form
	 */
	public static $group = 'commerce';

	/**
	 * @brief	Priority 1-5 (1 being highest) for this group compared to others
	 */
	public static $groupPriority = 4;

	/**
	 * @brief	Priority 1-5 (1 being highest) for this notification type compared to others in the same group
	 */
	public static $itemPriority = 1;

	/**
	 * Check for any issues we may need to send a notification about.
	 *
	 * @return	void
	 */
	public static function runChecksAndSendNotifications()
	{
		try
		{
			$stats = \IPS\xpaynowcheckout\XPaynowCheckout::collectAlertStats();
		}
		catch ( \Throwable $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_integrity_monitor' );
			return;
		}

		/* Webhook errors in last 24h */
		if ( (int) $stats['webhook_error_count_24h'] > 0 )
		{
			\IPS\core\AdminNotification::send( 'xpaynowcheckout', 'PaymentIntegrity', 'webhook_errors', TRUE );
		}
		else
		{
			\IPS\core\AdminNotification::remove( 'xpaynowcheckout', 'PaymentIntegrity', 'webhook_errors' );
		}

		/* Replay task stale — NULL means never ran yet (not stale), FALSE means genuinely stale */
		if ( $stats['replay_recent_run'] === FALSE )
		{
			\IPS\core\AdminNotification::send( 'xpaynowcheckout', 'PaymentIntegrity', 'replay_stale', TRUE );
		}
		elseif ( $stats['replay_recent_run'] === TRUE )
		{
			\IPS\core\AdminNotification::remove( 'xpaynowcheckout', 'PaymentIntegrity', 'replay_stale' );
		}

		/* PayNow-vs-IPS mismatches in last 30 days */
		if ( (int) $stats['mismatch_count_30d'] > 0 )
		{
			\IPS\core\AdminNotification::send( 'xpaynowcheckout', 'PaymentIntegrity', 'mismatches', TRUE );
		}
		else
		{
			\IPS\core\AdminNotification::remove( 'xpaynowcheckout', 'PaymentIntegrity', 'mismatches' );
		}
	}

	/**
	 * Title for settings.
	 *
	 * @return	string
	 */
	public static function settingsTitle()
	{
		return 'acp_notification_PaymentIntegrity';
	}

	/**
	 * Is this type of notification ever optional?
	 *
	 * @return	bool
	 */
	public static function mayBeOptional()
	{
		return TRUE;
	}

	/**
	 * Is this type of notification might recur?
	 *
	 * @return	bool
	 */
	public static function mayRecur()
	{
		return TRUE;
	}

	/**
	 * Can a member access this type of notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public static function permissionCheck( \IPS\Member $member )
	{
		return $member->hasAcpRestriction( 'xpaynowcheckout', 'monitoring', 'integrity_view' );
	}

	/**
	 * Can a member view this notification?
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function visibleTo( \IPS\Member $member )
	{
		return static::permissionCheck( $member );
	}

	/**
	 * Notification Title (full HTML, must be escaped where necessary).
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'pnc_alert_' . $this->extra . '_title' );
	}

	/**
	 * Notification Subtitle (no HTML).
	 *
	 * @return	string|NULL
	 */
	public function subtitle()
	{
		try
		{
			$stats = \IPS\xpaynowcheckout\XPaynowCheckout::collectAlertStats();
		}
		catch ( \Throwable $e )
		{
			return NULL;
		}

		switch ( $this->extra )
		{
			case 'webhook_errors':
				return \IPS\Member::loggedIn()->language()->addToStack( 'pnc_alert_webhook_errors_subtitle', FALSE, array( 'sprintf' => array( (int) $stats['webhook_error_count_24h'] ) ) );

			case 'replay_stale':
				return \IPS\Member::loggedIn()->language()->addToStack( 'pnc_alert_replay_stale_subtitle' );

			case 'mismatches':
				return \IPS\Member::loggedIn()->language()->addToStack( 'pnc_alert_mismatches_subtitle', FALSE, array( 'sprintf' => array( (int) $stats['mismatch_count_30d'] ) ) );

			default:
				return NULL;
		}
	}

	/**
	 * Notification Body (full HTML, must be escaped where necessary).
	 *
	 * @return	string
	 */
	public function body()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'pnc_alert_' . $this->extra . '_body' );
	}

	/**
	 * Severity.
	 *
	 * @return	string
	 */
	public function severity()
	{
		if ( \in_array( $this->extra, array( 'webhook_errors', 'replay_stale' ), TRUE ) )
		{
			return static::SEVERITY_HIGH;
		}

		return static::SEVERITY_NORMAL;
	}

	/**
	 * Dismissible?
	 *
	 * @return	string
	 */
	public function dismissible()
	{
		return static::DISMISSIBLE_TEMPORARY;
	}

	/**
	 * Style.
	 *
	 * @return	string
	 */
	public function style()
	{
		if ( $this->extra === 'webhook_errors' )
		{
			return static::STYLE_ERROR;
		}

		return static::STYLE_WARNING;
	}

	/**
	 * Quick link from popup menu.
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		return \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity', 'admin' );
	}

	/**
	 * Should this notification dismiss itself?
	 *
	 * @note	This is checked every time the notification shows. Should be lightweight.
	 * @return	bool
	 */
	public function selfDismiss()
	{
		try
		{
			$stats = \IPS\xpaynowcheckout\XPaynowCheckout::collectAlertStats();
		}
		catch ( \Throwable $e )
		{
			return FALSE;
		}

		switch ( $this->extra )
		{
			case 'webhook_errors':
				return (int) $stats['webhook_error_count_24h'] === 0;

			case 'replay_stale':
				return $stats['replay_recent_run'] === TRUE;

			case 'mismatches':
				return (int) $stats['mismatch_count_30d'] === 0;

			default:
				return FALSE;
		}
	}
}
