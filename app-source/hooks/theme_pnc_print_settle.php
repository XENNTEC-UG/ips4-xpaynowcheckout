//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class xpaynowcheckout_hook_theme_pnc_print_settle extends _HOOK_CLASS_
{

/* !Hook Data - DO NOT REMOVE */
public static function hookData() {
 return array_merge_recursive( array (
  'printInvoice' =>
  array (
    0 =>
    array (
      'selector' => '.ipsPrint > table',
      'type' => 'add_after',
      'content' => '{{if isset( $invoice->status_extra[ \'xpaynowcheckout_snapshot\' ] ) AND \is_array( $invoice->status_extra[ \'xpaynowcheckout_snapshot\' ] )}}
	{{$pncSnapshot = $invoice->status_extra[ \'xpaynowcheckout_snapshot\' ];}}
	<h2>{lang="xpaynowcheckout_settle_title"}</h2>
	<h3>{lang="xpaynowcheckout_settle_charge_summary"}</h3>
	<table>
		<tbody>
			<tr>
				<td><strong>{lang="xpaynowcheckout_settle_subtotal"}</strong></td>
				<td>{{if !empty( $pncSnapshot[ \'subtotal_display\' ] )}}{$pncSnapshot[ \'subtotal_display\' ]}{{else}}-{{endif}}</td>
			</tr>
			{{if !empty( $pncSnapshot[ \'discount_minor\' ] ) AND (int) $pncSnapshot[ \'discount_minor\' ] > 0}}
				<tr>
					<td><strong>{lang="xpaynowcheckout_settle_discount"}</strong></td>
					<td style=\'color: #22c55e;\'>-{{if !empty( $pncSnapshot[ \'discount_display\' ] )}}{$pncSnapshot[ \'discount_display\' ]}{{else}}{{$pnc_discountDisplay = \strtoupper( $pncSnapshot[ \'currency\' ] ) . \' \' . \number_format( (int) $pncSnapshot[ \'discount_minor\' ] / 100, 2 );}}{$pnc_discountDisplay}{{endif}}</td>
				</tr>
			{{endif}}
			<tr>
				<td><strong>{lang="xpaynowcheckout_settle_tax"}</strong></td>
				<td>{{if !empty( $pncSnapshot[ \'tax_display\' ] )}}{$pncSnapshot[ \'tax_display\' ]}{{else}}-{{endif}}</td>
			</tr>
			<tr style=\'border-top: 2px solid rgba(128,128,128,0.3);\'>
				<td><strong>{lang="xpaynowcheckout_settle_total_charged"}</strong></td>
				<td><strong>{{if !empty( $pncSnapshot[ \'total_display\' ] )}}{$pncSnapshot[ \'total_display\' ]}{{else}}-{{endif}}</strong></td>
			</tr>
			<tr>
				<td>{lang="xpaynowcheckout_settle_ips_total"}</td>
				<td>{$invoice->total}</td>
			</tr>
			{{if isset( $pncSnapshot[ \'has_total_mismatch\' ] ) AND $pncSnapshot[ \'has_total_mismatch\' ]}}
				<tr>
					<td><strong>{lang="xpaynowcheckout_settle_mismatch_title"}</strong></td>
					<td>{lang="xpaynowcheckout_settle_mismatch_warning"}</td>
				</tr>
			{{endif}}
		</tbody>
	</table>
	<h3>{lang="xpaynowcheckout_settle_payment_refs"}</h3>
	<table>
		<tbody>
			<tr>
				<td><strong>{lang="xpaynowcheckout_settle_captured_at"}</strong></td>
				<td>{{if !empty( $pncSnapshot[ \'captured_at_iso\' ] )}}{$pncSnapshot[ \'captured_at_iso\' ]}{{else}}-{{endif}}</td>
			</tr>
			<tr>
				<td><strong>{lang="xpaynowcheckout_settle_order_id"}</strong></td>
				<td>{{if !empty( $pncSnapshot[ \'paynow_order_id\' ] )}}{$pncSnapshot[ \'paynow_order_id\' ]}{{else}}-{{endif}}</td>
			</tr>
			{{if !empty( $pncSnapshot[ \'paynow_pretty_id\' ] )}}
			<tr>
				<td><strong>{lang="xpaynowcheckout_settle_pretty_id"}</strong></td>
				<td>{$pncSnapshot[ \'paynow_pretty_id\' ]}</td>
			</tr>
			{{endif}}
			{{if !empty( $pncSnapshot[ \'billing_name\' ] )}}
			<tr>
				<td><strong>{lang="xpaynowcheckout_settle_billing_name"}</strong></td>
				<td>{$pncSnapshot[ \'billing_name\' ]}</td>
			</tr>
			{{endif}}
			{{if !empty( $pncSnapshot[ \'billing_email\' ] )}}
			<tr>
				<td><strong>{lang="xpaynowcheckout_settle_billing_email"}</strong></td>
				<td>{$pncSnapshot[ \'billing_email\' ]}</td>
			</tr>
			{{endif}}
		</tbody>
	</table>
	<p><em>{lang="xpaynowcheckout_settle_source_truth"}</em></p>
{{endif}}',
    ),
  ),
), parent::hookData() );
}
/* End Hook Data */

}
