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
	 * @brief	Identifier
	 */
	public static $type = 'PaymentIntegrity';

	/**
	 * @brief	Application
	 */
	public static $app = 'xpaynowcheckout';

	/**
	 * Can the logged-in admin see this notification?
	 *
	 * @param	\IPS\Member	$member	Logged in member
	 * @return	bool
	 */
	public function visibleTo( \IPS\Member $member )
	{
		return $member->hasAcpRestriction( 'xpaynowcheckout', 'monitoring', 'integrity_view' );
	}

	/**
	 * Notification title
	 *
	 * @return	string
	 */
	public function title()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'pnc_alert_' . $this->extra . '_title' );
	}

	/**
	 * Notification body
	 *
	 * @return	string
	 */
	public function body()
	{
		return \IPS\Member::loggedIn()->language()->addToStack( 'pnc_alert_' . $this->extra . '_body' );
	}

	/**
	 * Severity
	 *
	 * @return	string
	 */
	public function severity()
	{
		return static::SEVERITY_WARNING;
	}

	/**
	 * Link to view the issue
	 *
	 * @return	\IPS\Http\Url
	 */
	public function link()
	{
		return \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity' );
	}
}
