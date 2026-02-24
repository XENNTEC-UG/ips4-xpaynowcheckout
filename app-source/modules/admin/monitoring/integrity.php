<?php
/**
 * @brief		PayNow Payment Integrity Panel
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
 * PayNow Integrity Monitor ACP Controller
 */
class _integrity extends \IPS\Dispatcher\Controller
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
		\IPS\Dispatcher::i()->checkAcpPermission( 'integrity_view' );
		parent::execute();
	}

	/**
	 * Default action — display the integrity panel.
	 *
	 * @return	void
	 */
	public function manage()
	{
		// TODO: Display webhook health, replay status, mismatch table
		// Pattern: same as xstripecheckout/xpolarcheckout integrity panels
		// - Webhook error count (24h)
		// - Replay task status
		// - IPS-vs-PayNow total mismatch table
		// - Webhook endpoint sync status
		// - Manual replay button (run now / dry run)
		// - Acknowledge errors button

		$stats = \IPS\xpaynowcheckout\XPaynowCheckout::collectAlertStats();

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_integrity_title' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'monitoring', 'xpaynowcheckout', 'admin' )->integrity( $stats );
	}

	/**
	 * Run webhook replay manually.
	 *
	 * @return	void
	 */
	protected function replay()
	{
		\IPS\Session::i()->csrfCheck();

		// TODO: Execute replay task logic
		// TODO: Log admin action

		\IPS\Output::i()->redirect(
			\IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity' ),
			'xpaynowcheckout_integrity_replay_success'
		);
	}

	/**
	 * Acknowledge webhook errors.
	 *
	 * @return	void
	 */
	protected function ackErrors()
	{
		\IPS\Session::i()->csrfCheck();

		\IPS\Data\Store::i()->pnc_webhook_errors_ack_at = \time();

		\IPS\Output::i()->redirect(
			\IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity' ),
			'pnc_integrity_ack_errors_done'
		);
	}
}
