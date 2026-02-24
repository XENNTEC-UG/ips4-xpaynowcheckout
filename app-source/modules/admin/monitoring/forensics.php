<?php
/**
 * @brief		PayNow Webhook Forensics Viewer
 * @author      https://xenntec.com/
 */

namespace IPS\xpaynowcheckout\modules\admin\monitoring;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PayNow Webhook Forensics ACP Controller
 */
class _forensics extends \IPS\Dispatcher\Controller
{
	/**
	 * @var	bool
	 */
	public static $csrfProtected = TRUE;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'forensics_view' );
		parent::execute();
	}

	/**
	 * Default action — display forensics table.
	 *
	 * @return	void
	 */
	public function manage()
	{
		// TODO: Table\Db for pnc_webhook_forensics with filters by failure_reason
		// Pattern: same as xstripecheckout forensics viewer

		$table = new \IPS\Helpers\Table\Db( 'pnc_webhook_forensics', \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=forensics' ) );
		$table->sortBy = 'created_at';
		$table->sortDirection = 'desc';
		$table->langPrefix = 'pnc_forensics_';

		$table->include = array( 'forensic_id', 'failure_reason', 'event_type', 'event_id', 'ip_address', 'http_status', 'created_at' );

		$table->filters = array(
			'pnc_forensics_filter_invalid_payload'   => "failure_reason='invalid_payload'",
			'pnc_forensics_filter_missing_signature'  => "failure_reason='missing_signature'",
			'pnc_forensics_filter_invalid_signature'  => "failure_reason='invalid_signature'",
			'pnc_forensics_filter_timestamp_too_old'  => "failure_reason='timestamp_too_old'",
		);

		$table->parsers = array(
			'created_at' => function( $val )
			{
				return \IPS\DateTime::ts( (int) $val )->localeDate() . ' ' . \IPS\DateTime::ts( (int) $val )->localeTime();
			},
		);

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'pnc_forensics_title' );
		\IPS\Output::i()->output = (string) $table;
	}

	/**
	 * Delete a single forensics entry.
	 *
	 * @return	void
	 */
	protected function delete()
	{
		\IPS\Session::i()->csrfCheck();

		$id = (int) \IPS\Request::i()->id;
		if ( $id > 0 )
		{
			\IPS\Db::i()->delete( 'pnc_webhook_forensics', array( 'forensic_id=?', $id ) );
		}

		\IPS\Output::i()->redirect(
			\IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=forensics' ),
			'pnc_forensics_deleted'
		);
	}
}
