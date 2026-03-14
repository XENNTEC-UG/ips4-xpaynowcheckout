//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class xpaynowcheckout_hook_code_memberProfileTab extends _HOOK_CLASS_
{
	/**
	 * Hook placeholder — getBlocks() is not used by the current MainTab implementation
	 * (which uses leftColumnBlocks/mainColumnBlocks instead). Block wiring may need
	 * a separate fix. Kept as a passthrough for forward compatibility.
	 *
	 * @return	array
	 */
	public function getBlocks()
	{
		return parent::getBlocks();
	}
}
