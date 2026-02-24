//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class xpaynowcheckout_hook_code_memberProfileTab extends _HOOK_CLASS_
{
	/**
	 * Add PayNow payment summary to the ACP member profile tab.
	 *
	 * @return	array
	 */
	public function getBlocks()
	{
		try
		{
			$return = parent::getBlocks();
			// PayNow payment summary block is registered via extensions.json
			return $return;
		}
		catch ( \Throwable $e )
		{
			return parent::getBlocks();
		}
	}
}
