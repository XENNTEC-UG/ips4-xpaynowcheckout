//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class xpaynowcheckout_hook_invoiceViewHook extends _HOOK_CLASS_
{
	/**
	 * Hook into invoice view to display PayNow settlement data.
	 *
	 * @return	void
	 */
	public function manage()
	{
		try
		{
			// TODO: If current invoice has a PayNow transaction, inject settlement snapshot data
			// into the output for the theme hook to render
			return parent::manage();
		}
		catch ( \Throwable $e )
		{
			return parent::manage();
		}
	}
}
