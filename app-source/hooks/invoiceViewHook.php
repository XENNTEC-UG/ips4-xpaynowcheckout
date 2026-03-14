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
	public function view()
	{
		parent::view();

		try
		{
			if ( !isset( $this->invoice ) )
			{
				return;
			}

			$output = \IPS\Output::i()->output;

			/* Only enhance invoices with PayNow settlement data */
			$extra = $this->invoice->status_extra;
			if ( !isset( $extra['xpaynowcheckout_snapshot'] ) || !\is_array( $extra['xpaynowcheckout_snapshot'] ) )
			{
				return;
			}

			$snapshot = $extra['xpaynowcheckout_snapshot'];

			/* Build PayNow Charge Summary */
			$chargeSummary = $this->_pncBuildChargeSummary( $snapshot );

			/* Build Payment & References */
			$paymentRefs = $this->_pncBuildPaymentRefs( $snapshot );

			/* Find the Order Details box and wrap in two-column layout */
			$output = $this->_pncWrapInColumns( $output, $chargeSummary );

			/* Insert Payment & References after the columns */
			$output = $this->_pncInsertPaymentRefs( $output, $paymentRefs );

			\IPS\Output::i()->output = $output;
		}
		catch ( \Throwable $e )
		{
			/* Silently fail — parent already set base output */
		}
	}

	/**
	 * Build PayNow Charge Summary HTML
	 *
	 * @param	array	$snapshot	PayNow snapshot data
	 * @return	string
	 */
	protected function _pncBuildChargeSummary( $snapshot )
	{
		$lang = \IPS\Member::loggedIn()->language();
		$html = '';

		$html .= "<div class='ipsBox'>";
		$html .= "<h2 class='ipsType_sectionTitle ipsType_reset'>" . $lang->addToStack( 'xpaynowcheckout_settle_title' ) . "</h2>";
		$html .= "<div class='ipsPad'>";
		$html .= "<h3 class='ipsType_minorHeading ipsType_reset'>" . $lang->addToStack( 'xpaynowcheckout_settle_charge_summary' ) . "</h3>";
		$html .= "<ul class='ipsDataList ipsDataList_reducedSpacing ipsSpacer_top ipsSpacer_half'>";

		/* Subtotal */
		$subtotalDisplay = !empty( $snapshot['subtotal_display'] ) ? htmlspecialchars( $snapshot['subtotal_display'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ) : '-';
		$html .= "<li class='ipsDataItem'>";
		$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_subtotal' ) . "</div>";
		$html .= "<div class='ipsDataItem_generic ipsType_right cNexusPrice' style='white-space:nowrap;'>{$subtotalDisplay}</div>";
		$html .= "</li>";

		/* Discount */
		$discountMinor = !empty( $snapshot['discount_minor'] ) ? (int) $snapshot['discount_minor'] : 0;
		if ( $discountMinor > 0 )
		{
			$discountDisplay = !empty( $snapshot['discount_display'] )
				? htmlspecialchars( $snapshot['discount_display'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE )
				: $this->_pncFormatMinorAmount( $discountMinor, $snapshot );

			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_discount' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right cNexusPrice' style='white-space:nowrap;color:#22c55e;'>-{$discountDisplay}</div>";
			$html .= "</li>";

			/* Net subtotal */
			$netMinor = (int) $snapshot['subtotal_minor'] - $discountMinor;
			$netDisplay = $this->_pncFormatMinorAmount( $netMinor, $snapshot );
			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main ipsType_light'>" . $lang->addToStack( 'xpaynowcheckout_settle_net_subtotal' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right cNexusPrice ipsType_light' style='white-space:nowrap;'>{$netDisplay}</div>";
			$html .= "</li>";
		}

		/* Tax */
		$taxDisplay = !empty( $snapshot['tax_display'] ) ? htmlspecialchars( $snapshot['tax_display'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ) : '-';
		$html .= "<li class='ipsDataItem'>";
		$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_tax' ) . "</div>";
		$html .= "<div class='ipsDataItem_generic ipsType_right cNexusPrice' style='white-space:nowrap;'>{$taxDisplay}</div>";
		$html .= "</li>";

		$html .= "</ul>";

		/* Total charged with divider */
		$totalDisplay = !empty( $snapshot['total_display'] ) ? htmlspecialchars( $snapshot['total_display'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ) : '-';
		$html .= "<div class='ipsSpacer_top ipsSpacer_half' style='border-top: 2px solid rgba(128,128,128,0.3); padding-top: 8px;'>";
		$html .= "<ul class='ipsDataList ipsDataList_reducedSpacing'>";
		$html .= "<li class='ipsDataItem'>";
		$html .= "<div class='ipsDataItem_main'><strong>" . $lang->addToStack( 'xpaynowcheckout_settle_total_charged' ) . "</strong></div>";
		$html .= "<div class='ipsDataItem_generic ipsType_right cNexusPrice' style='white-space:nowrap;'><strong>{$totalDisplay}</strong></div>";
		$html .= "</li>";
		$html .= "</ul>";
		$html .= "</div>";

		/* Tax explains difference info */
		if ( !empty( $snapshot['total_difference_tax_explained'] ) )
		{
			$html .= "<div class='ipsMessage ipsMessage_info ipsSpacer_top ipsSpacer_half'>";
			$html .= $lang->addToStack( 'xpaynowcheckout_settle_tax_explains_diff' );
			$html .= "</div>";
		}

		/* Total mismatch warning */
		if ( !empty( $snapshot['has_total_mismatch'] ) )
		{
			$html .= "<div class='ipsMessage ipsMessage_warning ipsSpacer_top ipsSpacer_half'>";
			$html .= "<strong>" . $lang->addToStack( 'xpaynowcheckout_settle_mismatch_title' ) . "</strong>: ";
			$html .= $lang->addToStack( 'xpaynowcheckout_settle_mismatch_warning' );
			$html .= "</div>";
		}

		$html .= "</div>"; /* ipsPad */
		$html .= "</div>"; /* ipsBox */

		return $html;
	}

	/**
	 * Build Payment & References HTML
	 *
	 * @param	array	$snapshot	PayNow snapshot data
	 * @return	string
	 */
	protected function _pncBuildPaymentRefs( $snapshot )
	{
		$lang = \IPS\Member::loggedIn()->language();
		$html = '';

		$html .= "<div class='ipsBox ipsMargin_top'>";
		$html .= "<h2 class='ipsType_sectionTitle ipsType_reset'>" . $lang->addToStack( 'xpaynowcheckout_settle_payment_refs' ) . "</h2>";
		$html .= "<div class='ipsPad'>";
		$html .= "<ul class='ipsDataList ipsDataList_reducedSpacing'>";

		/* Captured timestamp */
		$capturedDisplay = !empty( $snapshot['captured_at_iso'] ) ? htmlspecialchars( $snapshot['captured_at_iso'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ) : '-';
		$html .= "<li class='ipsDataItem'>";
		$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_captured_at' ) . "</div>";
		$html .= "<div class='ipsDataItem_generic ipsType_right' style='white-space:nowrap;'>{$capturedDisplay}</div>";
		$html .= "</li>";

		/* PayNow Order ID */
		$orderIdDisplay = !empty( $snapshot['paynow_order_id'] ) ? htmlspecialchars( $snapshot['paynow_order_id'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE ) : '-';
		$html .= "<li class='ipsDataItem'>";
		$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_order_id' ) . "</div>";
		$html .= "<div class='ipsDataItem_generic ipsType_right ipsType_light ipsType_small' style='white-space:nowrap;'>{$orderIdDisplay}</div>";
		$html .= "</li>";

		/* PayNow Pretty ID (human-readable order reference) */
		if ( !empty( $snapshot['paynow_pretty_id'] ) )
		{
			$prettyIdDisplay = htmlspecialchars( $snapshot['paynow_pretty_id'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE );
			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_pretty_id' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right' style='white-space:nowrap;'>{$prettyIdDisplay}</div>";
			$html .= "</li>";
		}

		/* Billing name */
		if ( !empty( $snapshot['billing_name'] ) )
		{
			$billingNameDisplay = htmlspecialchars( $snapshot['billing_name'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE );
			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_billing_name' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right ipsType_light' style='white-space:nowrap;'>{$billingNameDisplay}</div>";
			$html .= "</li>";
		}

		/* Billing email */
		if ( !empty( $snapshot['billing_email'] ) )
		{
			$emailDisplay = htmlspecialchars( $snapshot['billing_email'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE );
			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_billing_email' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right ipsType_light' style='white-space:nowrap;'>{$emailDisplay}</div>";
			$html .= "</li>";
		}

		/* Billing country */
		if ( !empty( $snapshot['billing_country'] ) )
		{
			$countryDisplay = htmlspecialchars( $snapshot['billing_country'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE );
			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_billing_country' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right ipsType_light' style='white-space:nowrap;'>{$countryDisplay}</div>";
			$html .= "</li>";
		}

		/* Completed at */
		if ( !empty( $snapshot['completed_at'] ) )
		{
			$completedDisplay = htmlspecialchars( $snapshot['completed_at'], ENT_QUOTES | ENT_DISALLOWED, 'UTF-8', FALSE );
			$html .= "<li class='ipsDataItem'>";
			$html .= "<div class='ipsDataItem_main'>" . $lang->addToStack( 'xpaynowcheckout_settle_completed_at' ) . "</div>";
			$html .= "<div class='ipsDataItem_generic ipsType_right ipsType_light' style='white-space:nowrap;'>{$completedDisplay}</div>";
			$html .= "</li>";
		}

		$html .= "</ul>";
		$html .= "</div>"; /* ipsPad */

		/* Source of truth footer */
		$html .= "<div class='ipsPad ipsAreaBackground_light'>";
		$html .= "<p class='ipsType_reset ipsType_medium ipsType_light'>" . $lang->addToStack( 'xpaynowcheckout_settle_source_truth' ) . "</p>";
		$html .= "</div>";

		$html .= "</div>"; /* ipsBox */

		return $html;
	}

	/**
	 * Find Order Details box and wrap in two-column layout
	 *
	 * @param	string	$output			Current page output
	 * @param	string	$chargeSummary	PayNow Charge Summary HTML
	 * @return	string
	 */
	protected function _pncWrapInColumns( $output, $chargeSummary )
	{
		/* Find the section title heading — unique marker for Order Details */
		$marker = 'ipsType_sectionTitle';
		$pos = mb_strpos( $output, $marker );
		if ( $pos === false )
		{
			return $output;
		}

		/* Go backwards to find the opening <div class="ipsBox"> */
		$before = mb_substr( $output, 0, $pos );
		$classPos = mb_strrpos( $before, 'class="ipsBox"' );
		if ( $classPos === false )
		{
			$classPos = mb_strrpos( $before, "class='ipsBox'" );
		}
		if ( $classPos === false )
		{
			return $output;
		}
		$divBefore = mb_substr( $output, 0, $classPos );
		$boxStart = mb_strrpos( $divBefore, '<div' );
		if ( $boxStart === false )
		{
			return $output;
		}

		/* Find the matching closing </div> */
		$boxEnd = $this->_pncFindClosingDiv( $output, $boxStart );
		if ( $boxEnd === false )
		{
			return $output;
		}

		$orderDetailsBox = mb_substr( $output, $boxStart, $boxEnd - $boxStart );

		/* Check if already inside a columns layout (shipments case or Stripe/Polar hook) */
		$contextBefore = mb_substr( $output, max( 0, $boxStart - 300 ), min( 300, $boxStart ) );
		if ( mb_strpos( $contextBefore, 'ipsColumn_fluid' ) !== false )
		{
			/* Already in columns — add PayNow summary after the Order Details box */
			$output = mb_substr( $output, 0, $boxEnd )
				. $chargeSummary
				. '<!-- pnc-columns-end -->'
				. mb_substr( $output, $boxEnd );
			return $output;
		}

		/* Standard mode — wrap in two-column layout */
		$twoColumn = "<div class='ipsColumns ipsColumns_collapsePhone'>"
			. "<div class='ipsColumn ipsColumn_fluid'>" . $orderDetailsBox . "</div>"
			. "<div class='ipsColumn ipsColumn_veryWide'>" . $chargeSummary . "</div>"
			. "</div>"
			. "<!-- pnc-columns-end -->";

		$output = mb_substr( $output, 0, $boxStart ) . $twoColumn . mb_substr( $output, $boxEnd );

		return $output;
	}

	/**
	 * Find the matching closing </div> for a div starting at $startPos
	 *
	 * @param	string	$html		HTML string
	 * @param	int		$startPos	Position of the opening <div
	 * @return	int|false			Position after the closing </div>, or false
	 */
	protected function _pncFindClosingDiv( $html, $startPos )
	{
		$depth = 0;
		$len = mb_strlen( $html );
		$i = $startPos;

		while ( $i < $len )
		{
			$nextOpen = mb_strpos( $html, '<div', $i );
			$nextClose = mb_strpos( $html, '</div>', $i );

			if ( $nextClose === false )
			{
				break;
			}

			if ( $nextOpen !== false && $nextOpen < $nextClose )
			{
				$depth++;
				$i = $nextOpen + 4;
			}
			else
			{
				$depth--;
				if ( $depth === 0 )
				{
					return $nextClose + 6;
				}
				$i = $nextClose + 6;
			}
		}

		return false;
	}

	/**
	 * Insert Payment & References section after the columns layout
	 *
	 * @param	string	$output			Current page output
	 * @param	string	$paymentRefs	Payment & References HTML
	 * @return	string
	 */
	protected function _pncInsertPaymentRefs( $output, $paymentRefs )
	{
		$marker = '<!-- pnc-columns-end -->';
		$pos = mb_strpos( $output, $marker );
		if ( $pos !== false )
		{
			$insertAt = $pos + mb_strlen( $marker );
			return mb_substr( $output, 0, $insertAt ) . $paymentRefs . mb_substr( $output, $insertAt );
		}

		/* Fallback: append at end */
		return $output . $paymentRefs;
	}

	/**
	 * Format minor amount for display.
	 *
	 * @param	int		$minor		Minor unit amount
	 * @param	array	$snapshot	Snapshot with currency
	 * @return	string
	 */
	protected function _pncFormatMinorAmount( $minor, $snapshot )
	{
		$currency = !empty( $snapshot['currency'] ) ? mb_strtoupper( $snapshot['currency'] ) : '';
		$decimals = $currency !== '' ? \IPS\nexus\Money::numberOfDecimalsForCurrency( $currency ) : 2;
		$divisor = \pow( 10, $decimals );
		return $currency . ' ' . number_format( (int) $minor / $divisor, $decimals );
	}
}
