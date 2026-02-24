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
	 * @return	string
	 */
	public function output()
	{
		$member = $this->member;

		// TODO: Query PayNow transactions for this member
		// TODO: Count chargebacks, refunds
		// TODO: Show ban status if banned for chargeback

		return \IPS\Theme::i()->getTemplate( 'monitoring', 'xpaynowcheckout', 'admin' )->paymentSummary( $member );
	}
}
