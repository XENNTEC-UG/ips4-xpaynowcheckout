//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class xpaynowcheckout_hook_theme_pnc_clients_settle extends _HOOK_CLASS_
{
	/* Theme hook (skin override) for front-end invoice settlement display.
	 * Injects PayNow settlement card into the Nexus invoice view.
	 *
	 * hookData() must declare : array for PHP 8 compatibility.
	 */

	public static function hookData() : array
	{
		// TODO: Return skin hook data array that injects settlement template
		// into class_nexus_front_clients
		return array();
	}
}
