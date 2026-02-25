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
	 * Render integrity dashboard.
	 *
	 * @return	void
	 */
	public function manage()
	{
		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_integrity_title' );
		$stats = $this->collectIntegrityStats();
		$replayNowUrl = \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity&do=runReplay', 'admin' )->csrf();
		$dryRunUrl = \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity&do=dryRunReplay', 'admin' )->csrf();

		\IPS\Output::i()->output = $this->renderDashboard( $stats, $replayNowUrl, $dryRunUrl );
	}

	/**
	 * Build the full dashboard HTML.
	 *
	 * @param	array			$stats		Integrity stats
	 * @param	\IPS\Http\Url	$replayUrl	CSRF-protected replay URL
	 * @param	\IPS\Http\Url	$dryRunUrl	CSRF-protected dry-run URL
	 * @return	string
	 */
	protected function renderDashboard( array $stats, $replayUrl, $dryRunUrl )
	{
		$h = '';

		$h .= '<style>
			.pnc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }
			.pnc-card { background: rgba(128,128,128,0.06); border: 1px solid rgba(128,128,128,0.15); border-radius: 8px; padding: 20px; }
			.pnc-card-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.55; margin-bottom: 8px; }
			.pnc-card-value { font-size: 28px; font-weight: 700; line-height: 1.2; }
			.pnc-card-sub { font-size: 12px; opacity: 0.45; margin-top: 4px; }
			.pnc-card--ok { border-left: 4px solid #22c55e; }
			.pnc-card--warn { border-left: 4px solid #f59e0b; }
			.pnc-card--err { border-left: 4px solid #ef4444; }
			.pnc-section { margin-bottom: 24px; }
			.pnc-section-title { font-size: 15px; font-weight: 600; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid rgba(128,128,128,0.15); }
			.pnc-empty { opacity: 0.45; font-size: 13px; padding: 16px 0; }
			.pnc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
			.pnc-table th { text-align: left; padding: 10px 12px; background: rgba(128,128,128,0.06); border-bottom: 2px solid rgba(128,128,128,0.15); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.3px; opacity: 0.6; }
			.pnc-table td { padding: 10px 12px; border-bottom: 1px solid rgba(128,128,128,0.08); }
			.pnc-table tr:hover td { background: rgba(128,128,128,0.04); }
			.pnc-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
			.pnc-tag--ok { background: rgba(34,197,94,0.15); color: #22c55e; }
			.pnc-tag--err { background: rgba(239,68,68,0.15); color: #ef4444; }
			.pnc-tag--warn { background: rgba(245,158,11,0.15); color: #d97706; }
			.pnc-actions { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
		</style>';

		/* --- Status cards --- */
		$h .= '<div class="pnc-grid">';

		/* Webhook config */
		$webhookClass = $stats['gateway_webhook_configured'] ? 'pnc-card--ok' : 'pnc-card--err';
		$webhookLabel = $stats['gateway_webhook_configured'] ? 'Configured' : 'Not configured';
		$webhookTag = $stats['gateway_webhook_configured'] ? 'pnc-tag--ok' : 'pnc-tag--err';
		$h .= '<div class="pnc-card ' . $webhookClass . '">'
			. '<div class="pnc-card-label">Webhook</div>'
			. '<div class="pnc-card-value"><span class="pnc-tag ' . $webhookTag . '">' . $webhookLabel . '</span></div>'
			. '<div class="pnc-card-sub">URL + signing secret</div>'
			. '</div>';

		/* Replay task */
		$replayClass = $stats['replay_recent_run'] ? 'pnc-card--ok' : 'pnc-card--warn';
		$replayTag = $stats['replay_recent_run'] ? 'pnc-tag--ok' : 'pnc-tag--warn';
		$replayLabel = $stats['replay_recent_run'] ? 'Healthy' : 'Stale';
		$replayTime = $this->formatTimestamp( $stats['replay_last_run_at'] );
		$h .= '<div class="pnc-card ' . $replayClass . '">'
			. '<div class="pnc-card-label">Replay Task</div>'
			. '<div class="pnc-card-value"><span class="pnc-tag ' . $replayTag . '">' . $replayLabel . '</span></div>'
			. '<div class="pnc-card-sub">Last run: ' . $this->escape( $replayTime ) . '</div>'
			. '</div>';

		/* Webhook errors */
		$errorCount = (int) $stats['webhook_error_count_24h'];
		$errorClass = $errorCount === 0 ? 'pnc-card--ok' : 'pnc-card--err';
		$h .= '<div class="pnc-card ' . $errorClass . '">'
			. '<div class="pnc-card-label">Errors (24h)</div>'
			. '<div class="pnc-card-value">' . $errorCount . '</div>'
			. '<div class="pnc-card-sub">Webhook + snapshot log entries</div>'
			. '</div>';

		/* Mismatches */
		$mismatch30 = (int) $stats['mismatch_count_30d'];
		$mismatchAll = (int) $stats['mismatch_count_all_time'];
		$mismatchClass = $mismatch30 === 0 ? 'pnc-card--ok' : 'pnc-card--warn';
		$h .= '<div class="pnc-card ' . $mismatchClass . '">'
			. '<div class="pnc-card-label">Mismatches (30d)</div>'
			. '<div class="pnc-card-value">' . $mismatch30 . '</div>'
			. '<div class="pnc-card-sub">' . $mismatchAll . ' all time</div>'
			. '</div>';

		$h .= '</div>';

		/* --- Replay details --- */
		$h .= '<div class="pnc-section">';
		$h .= '<div class="pnc-section-title">Webhook Replay</div>';

		$h .= '<div class="pnc-actions">';
		$h .= '<a href="' . $this->escape( (string) $replayUrl ) . '" class="ipsButton ipsButton_primary ipsButton_verySmall">' . $this->escape( \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_integrity_replay_now' ) ) . '</a>';
		$h .= '<a href="' . $this->escape( (string) $dryRunUrl ) . '" class="ipsButton ipsButton_alternate ipsButton_verySmall">' . $this->escape( \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_integrity_replay_dry_run' ) ) . '</a>';
		$h .= '<span style="font-size:12px;opacity:0.5;">Runs automatically every 15 minutes via task scheduler</span>';
		$h .= '</div>';

		$h .= '<table class="pnc-table">';
		$h .= '<tr><td style="width:240px;font-weight:600;">Events processed (last run)</td><td>' . $this->escape( (string) $stats['replay_last_replayed_count'] ) . '</td></tr>';
		$h .= '<tr><td style="font-weight:600;">Last event cursor</td><td>' . $this->escape( $this->formatTimestamp( $stats['replay_last_event_created'] ) ) . '</td></tr>';
		$h .= '<tr><td style="font-weight:600;">' . $this->escape( \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_replay_lookback' ) ) . '</td><td>' . $this->escape( (string) $stats['replay_config_lookback'] ) . 's</td></tr>';
		$h .= '<tr><td style="font-weight:600;">' . $this->escape( \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_replay_overlap' ) ) . '</td><td>' . $this->escape( (string) $stats['replay_config_overlap'] ) . 's</td></tr>';
		$h .= '<tr><td style="font-weight:600;">' . $this->escape( \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_replay_max_events' ) ) . '</td><td>' . $this->escape( (string) $stats['replay_config_max_events'] ) . '</td></tr>';
		$h .= '</table>';
		$h .= '</div>';

		/* --- Webhook errors --- */
		$h .= '<div class="pnc-section">';
		$h .= '<div class="pnc-section-title" style="display:flex;align-items:center;justify-content:space-between;">';
		$h .= '<span>Recent Webhook Errors</span>';
		if ( $errorCount > 0 )
		{
			$ackUrl = \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity&do=ackErrors', 'admin' )->csrf();
			$h .= '<a href="' . $this->escape( (string) $ackUrl ) . '" class="ipsButton ipsButton_alternate ipsButton_verySmall">' . $this->escape( \IPS\Member::loggedIn()->language()->addToStack( 'pnc_integrity_ack_errors' ) ) . '</a>';
		}
		$h .= '</div>';
		$h .= $this->renderWebhookErrorTable( $stats['recent_webhook_errors'] );
		$h .= '</div>';

		/* --- Mismatch table --- */
		$h .= '<div class="pnc-section">';
		$h .= '<div class="pnc-section-title">PayNow vs IPS Total Mismatches</div>';
		$h .= $this->renderMismatchTable( $stats['recent_mismatch_rows'] );
		$h .= '</div>';

		return $h;
	}

	/**
	 * Manually trigger webhook replay task now.
	 *
	 * @return	void
	 */
	protected function runReplay()
	{
		\IPS\Session::i()->csrfCheck();
		$redirectUrl = \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity', 'admin' );

		try
		{
			$task = new \IPS\xpaynowcheckout\tasks\pncWebhookReplay;
			$result = $task->execute();
			\IPS\Session::i()->log( 'acplogs__xpaynowcheckout_integrity_replay' );

			$message = $result ? 'xpaynowcheckout_integrity_replay_success' : 'xpaynowcheckout_integrity_replay_no_events';
			\IPS\Output::i()->redirect( $redirectUrl, $message );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_integrity_replay' );
			\IPS\Output::i()->redirect( $redirectUrl, 'xpaynowcheckout_integrity_replay_failed' );
		}
	}

	/**
	 * Run webhook replay in dry-run mode.
	 *
	 * @return	void
	 */
	protected function dryRunReplay()
	{
		\IPS\Session::i()->csrfCheck();
		$redirectUrl = \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity', 'admin' );

		try
		{
			$task = new \IPS\xpaynowcheckout\tasks\pncWebhookReplay;
			$result = $task->execute( TRUE );
			\IPS\Session::i()->log( 'acplogs__xpaynowcheckout_integrity_dry_run' );

			$count = isset( $result['count'] ) ? (int) $result['count'] : 0;
			$message = $count > 0
				? \IPS\Member::loggedIn()->language()->addToStack( 'pnc_integrity_dry_run_result', FALSE, array( 'sprintf' => array( $count ) ) )
				: \IPS\Member::loggedIn()->language()->addToStack( 'pnc_integrity_dry_run_none' );

			\IPS\Output::i()->redirect( $redirectUrl, $message );
		}
		catch ( \Exception $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout_integrity_replay' );
			\IPS\Output::i()->redirect( $redirectUrl, 'xpaynowcheckout_integrity_replay_failed' );
		}
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
		\IPS\core\AdminNotification::remove( 'xpaynowcheckout', 'PaymentIntegrity', 'webhook_errors' );

		\IPS\Output::i()->redirect(
			\IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity', 'admin' ),
			'pnc_integrity_ack_errors_done'
		);
	}

	/**
	 * Build data model for the panel.
	 *
	 * @return	array
	 */
	protected function collectIntegrityStats()
	{
		$stats = array(
			'gateway_webhook_configured' => FALSE,
			'replay_last_run_at' => NULL,
			'replay_last_event_created' => NULL,
			'replay_last_replayed_count' => 0,
			'replay_recent_run' => FALSE,
			'replay_config_lookback' => 3600,
			'replay_config_overlap' => 300,
			'replay_config_max_events' => 100,
			'webhook_error_count_24h' => 0,
			'mismatch_count_all_time' => 0,
			'mismatch_count_30d' => 0,
			'recent_webhook_errors' => array(),
			'recent_mismatch_rows' => array(),
		);

		$gatewaySettings = $this->loadGatewaySettings();
		if ( \is_array( $gatewaySettings ) )
		{
			$stats['gateway_webhook_configured'] = !empty( $gatewaySettings['webhook_url'] ) && !empty( $gatewaySettings['webhook_secret'] );

			if ( isset( $gatewaySettings['replay_lookback'] ) )
			{
				$stats['replay_config_lookback'] = \max( 300, \min( 86400, (int) $gatewaySettings['replay_lookback'] ) );
			}
			if ( isset( $gatewaySettings['replay_overlap'] ) )
			{
				$stats['replay_config_overlap'] = \max( 60, \min( 1800, (int) $gatewaySettings['replay_overlap'] ) );
			}
			if ( isset( $gatewaySettings['replay_max_events'] ) )
			{
				$stats['replay_config_max_events'] = \max( 10, \min( 500, (int) $gatewaySettings['replay_max_events'] ) );
			}
		}

		if ( isset( \IPS\Data\Store::i()->xpaynowcheckout_webhook_replay_state ) && \is_array( \IPS\Data\Store::i()->xpaynowcheckout_webhook_replay_state ) )
		{
			$replayState = \IPS\Data\Store::i()->xpaynowcheckout_webhook_replay_state;
			$stats['replay_last_run_at'] = ( isset( $replayState['last_run_at'] ) && \is_numeric( $replayState['last_run_at'] ) ) ? (int) $replayState['last_run_at'] : NULL;
			$stats['replay_last_event_created'] = ( isset( $replayState['last_event_created'] ) && \is_numeric( $replayState['last_event_created'] ) ) ? (int) $replayState['last_event_created'] : NULL;
			$stats['replay_last_replayed_count'] = ( isset( $replayState['last_replayed_count'] ) && \is_numeric( $replayState['last_replayed_count'] ) ) ? (int) $replayState['last_replayed_count'] : 0;
			$stats['replay_recent_run'] = ( $stats['replay_last_run_at'] !== NULL && ( \time() - $stats['replay_last_run_at'] ) <= $stats['replay_config_lookback'] );
		}

		$dayAgo = \time() - 86400;
		$monthAgo = \time() - ( 30 * 86400 );

		$ackAt = 0;
		if ( isset( \IPS\Data\Store::i()->pnc_webhook_errors_ack_at ) )
		{
			$ackAt = (int) \IPS\Data\Store::i()->pnc_webhook_errors_ack_at;
		}
		$errorsSince = \max( $dayAgo, $ackAt );

		try
		{
			$stats['webhook_error_count_24h'] = (int) \IPS\Db::i()->select(
				'COUNT(*)',
				'core_log',
				array( '( category=? OR category=? ) AND time>?', 'xpaynowcheckout_webhook', 'xpaynowcheckout_snapshot', $errorsSince )
			)->first();

			foreach ( \IPS\Db::i()->select(
				'time,category,message',
				'core_log',
				array( '( category=? OR category=? ) AND time>?', 'xpaynowcheckout_webhook', 'xpaynowcheckout_snapshot', $errorsSince ),
				'id DESC',
				10
			) as $row )
			{
				$stats['recent_webhook_errors'][] = $row;
			}
		}
		catch ( \Exception $e ) {}

		$mismatchWhere = "JSON_UNQUOTE(JSON_EXTRACT(t_extra, '$.xpaynowcheckout_snapshot.has_total_mismatch'))='true'";
		try
		{
			$stats['mismatch_count_all_time'] = (int) \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', $mismatchWhere )->first();
			$stats['mismatch_count_30d'] = (int) \IPS\Db::i()->select( 'COUNT(*)', 'nexus_transactions', array( "{$mismatchWhere} AND t_date>?", $monthAgo ) )->first();

			$fields = "t_id,t_invoice,t_date,JSON_UNQUOTE(JSON_EXTRACT(t_extra, '$.xpaynowcheckout_snapshot.amount_total_display')) AS paynow_total_display,JSON_UNQUOTE(JSON_EXTRACT(t_extra, '$.xpaynowcheckout_snapshot.ips_invoice_total_display')) AS ips_total_display,JSON_UNQUOTE(JSON_EXTRACT(t_extra, '$.xpaynowcheckout_snapshot.total_mismatch_display')) AS mismatch_display";
			foreach ( \IPS\Db::i()->select( $fields, 'nexus_transactions', $mismatchWhere, 't_id DESC', 10 ) as $row )
			{
				$stats['recent_mismatch_rows'][] = $row;
			}
		}
		catch ( \Exception $e ) {}

		return $stats;
	}

	/**
	 * Resolve active XPaynowCheckout gateway settings.
	 *
	 * @return	array|NULL
	 */
	protected function loadGatewaySettings()
	{
		foreach ( \IPS\nexus\Gateway::roots() as $gateway )
		{
			if ( $gateway instanceof \IPS\xpaynowcheckout\XPaynowCheckout )
			{
				$settings = \json_decode( $gateway->settings, TRUE );
				return \is_array( $settings ) ? $settings : NULL;
			}
		}

		return NULL;
	}

	/**
	 * Render recent webhook errors table.
	 *
	 * @param	array	$rows	Error rows
	 * @return	string
	 */
	protected function renderWebhookErrorTable( array $rows )
	{
		if ( !\count( $rows ) )
		{
			return '<p class="pnc-empty">No webhook or snapshot processing errors in the last 24 hours.</p>';
		}

		$output = '<table class="pnc-table">';
		$output .= '<thead><tr><th>Time (UTC)</th><th>Category</th><th>Message</th></tr></thead><tbody>';
		foreach ( $rows as $row )
		{
			$message = isset( $row['message'] ) ? (string) $row['message'] : '';
			$message = \trim( \preg_replace( '/\s+/', ' ', $message ) );
			if ( \mb_strlen( $message ) > 200 )
			{
				$message = \mb_substr( $message, 0, 197 ) . '...';
			}

			$cat = isset( $row['category'] ) ? (string) $row['category'] : '';
			$catShort = \str_replace( 'xpaynowcheckout_', '', $cat );

			$output .= '<tr>'
				. '<td style="white-space:nowrap;">' . $this->escape( $this->formatTimestamp( isset( $row['time'] ) ? (int) $row['time'] : NULL ) ) . '</td>'
				. '<td><span class="pnc-tag pnc-tag--err">' . $this->escape( $catShort ) . '</span></td>'
				. '<td>' . $this->escape( $message ) . '</td>'
				. '</tr>';
		}
		$output .= '</tbody></table>';

		return $output;
	}

	/**
	 * Render recent mismatch rows table.
	 *
	 * @param	array	$rows	Mismatch rows
	 * @return	string
	 */
	protected function renderMismatchTable( array $rows )
	{
		if ( !\count( $rows ) )
		{
			return '<p class="pnc-empty">No PayNow-vs-IPS total mismatches detected.</p>';
		}

		$output = '<table class="pnc-table">';
		$output .= '<thead><tr><th>Transaction</th><th>Invoice</th><th>Date</th><th>PayNow Total</th><th>IPS Total</th><th>Difference</th></tr></thead><tbody>';
		foreach ( $rows as $row )
		{
			$txUrl = \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=transactions&do=view&id=' . (int) $row['t_id'], 'admin' );
			$invUrl = \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=invoices&do=view&id=' . (int) $row['t_invoice'], 'admin' );
			$date = isset( $row['t_date'] ) ? $this->formatTimestamp( (int) $row['t_date'] ) : '-';

			$output .= '<tr>'
				. '<td><a href="' . $this->escape( (string) $txUrl ) . '">#' . $this->escape( (string) $row['t_id'] ) . '</a></td>'
				. '<td><a href="' . $this->escape( (string) $invUrl ) . '">#' . $this->escape( (string) $row['t_invoice'] ) . '</a></td>'
				. '<td style="white-space:nowrap;">' . $this->escape( $date ) . '</td>'
				. '<td>' . $this->escape( isset( $row['paynow_total_display'] ) ? (string) $row['paynow_total_display'] : '-' ) . '</td>'
				. '<td>' . $this->escape( isset( $row['ips_total_display'] ) ? (string) $row['ips_total_display'] : '-' ) . '</td>'
				. '<td><span class="pnc-tag pnc-tag--warn">' . $this->escape( isset( $row['mismatch_display'] ) ? (string) $row['mismatch_display'] : '-' ) . '</span></td>'
				. '</tr>';
		}
		$output .= '</tbody></table>';

		return $output;
	}

	/**
	 * Format unix timestamp for ACP display.
	 *
	 * @param	int|NULL	$timestamp	Unix timestamp
	 * @return	string
	 */
	protected function formatTimestamp( $timestamp )
	{
		if ( $timestamp === NULL OR $timestamp <= 0 )
		{
			return '-';
		}

		return \gmdate( 'Y-m-d H:i:s', (int) $timestamp ) . ' UTC';
	}

	/**
	 * Escape HTML.
	 *
	 * @param	string	$value	Value
	 * @return	string
	 */
	protected function escape( $value )
	{
		return \htmlspecialchars( (string) $value, ENT_QUOTES | ENT_DISALLOWED, 'UTF-8' );
	}
}
