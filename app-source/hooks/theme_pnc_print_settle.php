//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class xpaynowcheckout_hook_theme_pnc_print_settle extends _HOOK_CLASS_
{
	/* Theme hook (skin override) for print invoice settlement display.
	 * Injects PayNow settlement data into the Nexus printable invoice.
	 *
	 * hookData() must declare : array for PHP 8 compatibility.
	 */

	public static function hookData() : array
	{
		// TODO: Return skin hook data for print invoice settlement
		return array();
	}
}
