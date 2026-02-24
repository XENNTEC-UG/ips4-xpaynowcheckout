//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class xpaynowcheckout_hook_code_GatewayModel extends _HOOK_CLASS_
{
	/**
	 * Register the PayNow Checkout gateway class in Gateway::$gatewayMap.
	 *
	 * @return	array
	 */
	public static function gatewayClasses()
	{
		try
		{
			$return = parent::gatewayClasses();
			$return['XPaynowCheckout'] = 'IPS\xpaynowcheckout\XPaynowCheckout';
			return $return;
		}
		catch ( \Throwable $e )
		{
			return parent::gatewayClasses();
		}
	}
}
