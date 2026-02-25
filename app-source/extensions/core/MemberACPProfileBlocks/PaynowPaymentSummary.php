<?php
/**
 * @brief		PayNow Payment Summary ACP Profile Block
 * @author      https://xenntec.com/
 */

namespace IPS\xpaynowcheckout\extensions\core\MemberACPProfileBlocks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Displays PayNow payment summary (chargebacks, refunds) on ACP member profile.
 */
class _PaynowPaymentSummary extends \IPS\core\MemberACPProfile\Block
{
	/**
	 * Get block output
	 *
	 * @return	string|NULL
	 */
	public function output()
	{
		try
		{
			/* Find PayNow gateway IDs */
			$gatewayIds = array();
			foreach ( \IPS\nexus\Gateway::roots() as $gateway )
			{
				if ( $gateway instanceof \IPS\xpaynowcheckout\XPaynowCheckout )
				{
					$gatewayIds[] = $gateway->id;
				}
			}

			if ( empty( $gatewayIds ) )
			{
				return NULL;
			}

			/* Query transactions for this member with dispute/refund status */
			$where = array(
				array( \IPS\Db::i()->in( 't_method', $gatewayIds ) ),
				array( 't_member=?', $this->member->member_id ),
				array( \IPS\Db::i()->in( 't_status', array( 'dspd', 'rfnd', 'prfd' ) ) ),
			);

			$disputeCount = 0;
			$refundCount = 0;
			$latestChargeback = NULL;

			$transactions = \IPS\Db::i()->select( 't_id, t_status, t_extra', 'nexus_transactions', $where, 't_id DESC' );
			foreach ( $transactions as $row )
			{
				$extra = json_decode( $row['t_extra'], TRUE );

				if ( $row['t_status'] === 'dspd' )
				{
					$disputeCount++;
					if ( $latestChargeback === NULL AND isset( $extra['xpaynowcheckout_chargeback'] ) AND \is_array( $extra['xpaynowcheckout_chargeback'] ) )
					{
						$latestChargeback = $extra['xpaynowcheckout_chargeback'];
					}
				}
				elseif ( $row['t_status'] === 'rfnd' OR $row['t_status'] === 'prfd' )
				{
					$refundCount++;
				}
			}

			/* Nothing to show */
			if ( $disputeCount === 0 AND $refundCount === 0 )
			{
				return NULL;
			}

			/* Check ban status */
			$isBanned = ( $this->member->temp_ban == -1 );

			/* Build output HTML (inline, matching xstripecheckout sibling pattern) */
			$html = "<div class='ipsBox'>";
			$html .= "<h2 class='ipsType_sectionTitle ipsType_reset'>" . \IPS\Member::loggedIn()->language()->addToStack( 'memberACPProfileTitle_xpaynowcheckout_PaynowPaymentSummary' ) . "</h2>";
			$html .= "<div class='ipsPad'><ul class='ipsDataList ipsDataList_reducedSpacing'>";

			/* Chargebacks row */
			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main'>" . \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_chargebacks_count' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right'>";
			if ( $disputeCount > 0 )
			{
				$html .= "<span class='ipsType_warning'><strong>{$disputeCount}</strong></span>";
			}
			else
			{
				$html .= "0";
			}
			$html .= "</div></li>";

			/* Latest chargeback detail */
			if ( $latestChargeback !== NULL )
			{
				$html .= "<li class='ipsDataItem'>";
				$html .= "<div class='ipsDataItem_main'>" . \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_chargebacks_count' ) . " (latest)</div>";
				$html .= "<div class='ipsDataItem_generic ipsType_right'>";
				if ( isset( $latestChargeback['reason'] ) AND $latestChargeback['reason'] !== '' )
				{
					$html .= htmlspecialchars( $latestChargeback['reason'] );
				}
				if ( isset( $latestChargeback['created_at'] ) )
				{
					$html .= " (" . \IPS\DateTime::ts( (int) $latestChargeback['created_at'] )->localeDate() . ")";
				}
				$html .= "</div></li>";
			}

			/* Refunds row */
			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main'>" . \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_refunds_count' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right'>{$refundCount}</div>";
			$html .= "</li>";

			/* Ban status */
			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main'>" . \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_ban_status' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right'>";
			if ( $isBanned )
			{
				$html .= "<span class='ipsType_warning'>" . \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_banned_chargeback' ) . "</span>";
			}
			else
			{
				$html .= \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_not_banned' );
			}
			$html .= "</div></li>";

			$html .= "</ul>";

			/* Link to integrity panel */
			$integrityUrl = \IPS\Http\Url::internal( 'app=xpaynowcheckout&module=monitoring&controller=integrity' );
			$html .= "<p class='ipsType_reset ipsType_medium ipsSpacer_top ipsSpacer_half'>";
			$html .= "<a href='" . htmlspecialchars( (string) $integrityUrl ) . "'>" . \IPS\Member::loggedIn()->language()->addToStack( 'xpaynowcheckout_view_integrity' ) . "</a>";
			$html .= "</p>";

			$html .= "</div></div>";

			return $html;
		}
		catch ( \Throwable $e )
		{
			\IPS\Log::log( $e, 'xpaynowcheckout' );
			return NULL;
		}
	}
}
