<?php

$lang = array(

'__app_xpaynowcheckout'	=> 'X PayNow Checkout',
'gateway__XPaynowCheckout'	=> 'X PayNow Checkout',
'gateway__XPaynowCheckout_desc'	=> 'PayNow.gg Checkout Gateway for Invision Community',

/* ACP Settings - Credentials */
'xpaynowcheckout_credentials'	=> 'API Credentials',
'xpaynowcheckout_api_key'	=> 'API Key',
'xpaynowcheckout_api_key_desc'	=> 'API Key from your PayNow dashboard. Used for store management API calls.',
'xpaynowcheckout_store_id'	=> 'Store ID',
'xpaynowcheckout_store_id_desc'	=> 'Your PayNow store identifier (Flake ID format).',
'xpaynowcheckout_default_product_id'	=> 'Default Product ID',
'xpaynowcheckout_default_product_id_desc'	=> 'Optional. A fallback PayNow product ID. When left empty, invoice items are sent as inline products with their IPS names and prices.',

/* ACP Settings - Webhook */
'xpaynowcheckout_webhook'	=> 'Webhook',
'xpaynowcheckout_webhook_url'	=> 'Webhook URL',
'xpaynowcheckout_webhook_url_desc'	=> 'Auto-generated after first save. PayNow will POST events to this URL.',
'xpaynowcheckout_webhook_secret'	=> 'Webhook Secret',
'xpaynowcheckout_webhook_secret_desc'	=> 'Auto-generated after first save. Used to verify webhook signatures.',
'module__xpaynowcheckout_webhook'	=> 'PayNow Checkout Webhook',

/* ACP Settings - Checkout */
'xpaynowcheckout_checkout_settings'	=> 'Checkout Settings',
'xpaynowcheckout_return_url'	=> 'Success return URL',
'xpaynowcheckout_return_url_desc'	=> 'URL the customer is redirected to after successful payment. Leave empty to use the IPS transaction URL.',
'xpaynowcheckout_cancel_url'	=> 'Cancel URL',
'xpaynowcheckout_cancel_url_desc'	=> 'URL the customer is redirected to if they cancel. Leave empty to use the IPS invoice checkout URL.',

/* ACP Settings - Fraud Protection */
'xpaynowcheckout_fraud_protection'	=> 'Fraud Protection',
'xpaynowcheckout_chargeback_ban'	=> 'Ban member on chargeback?',
'xpaynowcheckout_chargeback_ban_desc'	=> 'Permanently ban the member when a chargeback is opened against their payment. Admin can unban from the member profile.',

/* ACP Settings - Replay */
'xpaynowcheckout_replay'	=> 'Webhook Replay',
'xpaynowcheckout_replay_lookback'	=> 'Lookback window (seconds)',
'xpaynowcheckout_replay_lookback_desc'	=> 'How far back to look for webhook events on first run. Default: 3600 (1 hour).',
'xpaynowcheckout_replay_overlap'	=> 'Overlap window (seconds)',
'xpaynowcheckout_replay_overlap_desc'	=> 'Overlap when advancing the replay cursor. Default: 300 (5 minutes).',
'xpaynowcheckout_replay_max_events'	=> 'Max events per run',
'xpaynowcheckout_replay_max_events_desc'	=> 'Maximum webhook events per replay execution. Default: 100.',

/* Invoice / Settlement */
'xpaynowcheckout_payment_invoice'	=> 'Payment for Invoice #%s',
'xpaynowcheckout_settle_title'	=> 'PayNow Settlement',
'xpaynowcheckout_settle_charge_summary'	=> 'Charge Summary',
'xpaynowcheckout_settle_subtotal'	=> 'Subtotal',
'xpaynowcheckout_settle_discount'	=> 'Discount',
'xpaynowcheckout_settle_net_subtotal'	=> 'Net subtotal',
'xpaynowcheckout_settle_tax'	=> 'Tax',
'xpaynowcheckout_settle_total_charged'	=> 'Total charged',
'xpaynowcheckout_settle_ips_total'	=> 'Invoice total (IPS)',
'xpaynowcheckout_settle_mismatch_title'	=> 'Total mismatch',
'xpaynowcheckout_settle_mismatch_warning'	=> 'The PayNow total does not match the IPS invoice total.',
'xpaynowcheckout_settle_tax_explains_diff'	=> 'The difference between the PayNow total and IPS total is fully explained by tax collected at the provider.',
'xpaynowcheckout_settle_payment_refs'	=> 'Payment & References',
'xpaynowcheckout_settle_order_id'	=> 'PayNow Order ID',
'xpaynowcheckout_settle_pretty_id'	=> 'Order Reference',
'xpaynowcheckout_settle_payment_id'	=> 'Payment ID',
'xpaynowcheckout_settle_payment_method'	=> 'Payment method',
'xpaynowcheckout_settle_captured_at'	=> 'Captured',
'xpaynowcheckout_settle_completed_at'	=> 'Completed',
'xpaynowcheckout_settle_billing_name'	=> 'Billing name',
'xpaynowcheckout_settle_billing_email'	=> 'Billing email',
'xpaynowcheckout_settle_billing_country'	=> 'Billing country',
'xpaynowcheckout_settle_source_truth'	=> 'Settlement data sourced from PayNow at time of payment capture. This is the provider source of truth.',
'xpaynowcheckout_provider_charged_label'	=> 'Charged via PayNow',
'xpaynowcheckout_ips_invoice_total_label'	=> 'IPS invoice total',

/* Coupon forwarding */
'xpaynowcheckout_coupon_discount'	=> 'Discount',

/* Refund reasons */
'xpaynowcheckout_reason_customer_request'	=> 'Requested by customer',
'xpaynowcheckout_reason_duplicate'	=> 'Duplicate payment',
'xpaynowcheckout_reason_fraudulent'	=> 'Fraudulent',

/* ACP Monitoring - Integrity */
'module__xpaynowcheckout_monitoring'	=> 'Monitoring',
'menu__xpaynowcheckout_monitoring'	=> 'X PayNow Checkout',
'menu__xpaynowcheckout_monitoring_integrity'	=> 'Payment Integrity',
'xpaynowcheckout_integrity_title'	=> 'PayNow Checkout Integrity',
'xpaynowcheckout_integrity_intro'	=> 'Operational visibility panel for PayNow webhook health, replay status, and PayNow-vs-IPS settlement mismatches.',
'xpaynowcheckout_integrity_replay_now'	=> 'Run Webhook Replay Now',
'xpaynowcheckout_integrity_replay_success'	=> 'Webhook replay executed successfully.',
'xpaynowcheckout_integrity_replay_no_events'	=> 'No replayable events found.',
'xpaynowcheckout_integrity_replay_failed'	=> 'Webhook replay failed. Check logs.',
'xpaynowcheckout_integrity_replay_dry_run'	=> 'Dry Run',
'integrity_view'	=> 'View integrity panel',

/* ACP Monitoring - Forensics */
'menu__xpaynowcheckout_monitoring_forensics'	=> 'Webhook Forensics',
'pnc_forensics_title'	=> 'Webhook Forensics Log',
'forensics_view'	=> 'View webhook forensics',
'pnc_forensics_forensic_id'	=> 'ID',
'pnc_forensics_failure_reason'	=> 'Failure Reason',
'pnc_forensics_event_type'	=> 'Event Type',
'pnc_forensics_event_id'	=> 'Event ID',
'pnc_forensics_ip_address'	=> 'IP Address',
'pnc_forensics_http_status'	=> 'HTTP Status',
'pnc_forensics_created_at'	=> 'Date',
'pnc_forensics_filter_invalid_payload'	=> 'Invalid Payload',
'pnc_forensics_filter_missing_signature'	=> 'Missing Signature',
'pnc_forensics_filter_invalid_signature'	=> 'Invalid Signature',
'pnc_forensics_filter_timestamp_too_old'	=> 'Timestamp Too Old',
'pnc_forensics_deleted'	=> 'Entry deleted.',

/* Acknowledge + alerts */
'pnc_integrity_ack_errors'	=> 'Acknowledge Errors',
'pnc_integrity_ack_errors_done'	=> 'Webhook errors acknowledged.',
'pnc_integrity_delete_errors'	=> 'Delete Errors',
'pnc_integrity_delete_errors_done'	=> 'Webhook errors deleted from system log.',
'acp_notification_PaymentIntegrity'	=> 'PayNow Payment Integrity',
'pnc_alert_webhook_errors_title'	=> 'PayNow Webhook Errors Detected',
'pnc_alert_replay_stale_title'	=> 'Webhook Replay Task Stale',
'pnc_alert_mismatches_title'	=> 'Payment Total Mismatches Detected',
'pnc_alert_webhook_errors_body'	=> 'Check the PayNow Checkout integrity panel for error details.',
'pnc_alert_replay_stale_body'	=> 'The replay task may not be running. Check task scheduler and integrity panel.',
'pnc_alert_mismatches_body'	=> 'PayNow-vs-IPS total mismatches found. Review the integrity panel.',
'pnc_alert_webhook_errors_subtitle'	=> '%d webhook error(s) in the last 24 hours.',
'pnc_alert_replay_stale_subtitle'	=> 'The replay task has not run within its expected window.',
'pnc_alert_mismatches_subtitle'	=> '%d PayNow-vs-IPS mismatch(es) in the last 30 days.',

/* Integrity - Dry Run */
'pnc_integrity_dry_run_result'	=> 'Dry run found %d replayable event(s).',
'pnc_integrity_dry_run_none'	=> 'Dry run: no replayable events found.',

/* ACP log keys */
'acplogs__xpaynowcheckout_integrity_replay'	=> 'Ran PayNow webhook replay manually.',
'acplogs__xpaynowcheckout_integrity_dry_run'	=> 'Ran PayNow webhook replay dry run.',

/* Tasks */
'task__pncIntegrityMonitor'	=> 'PayNow Checkout Integrity Monitor',
'task__pncWebhookReplay'	=> 'PayNow Checkout Webhook Replay',

/* Member profile block */
'memberACPProfileTitle_xpaynowcheckout_PaynowPaymentSummary'	=> 'PayNow Payments',
'xpaynowcheckout_chargebacks_count'	=> 'Chargebacks',
'xpaynowcheckout_refunds_count'	=> 'Refunds',
'xpaynowcheckout_ban_status'	=> 'Ban status',
'xpaynowcheckout_banned_chargeback'	=> 'Banned (chargeback)',
'xpaynowcheckout_not_banned'	=> 'Not banned',
'xpaynowcheckout_view_integrity'	=> 'View integrity panel',

/* Chargeback resolution */
'xpaynowcheckout_dispute_closed_won'	=> 'Chargeback resolved (won)',
'xpaynowcheckout_dispute_closed_lost'	=> 'Chargeback resolved (lost)',

/* Error messages */
'xpaynowcheckout_invalid_settings'	=> 'PayNow Checkout gateway settings are invalid.',
'xpaynowcheckout_missing_required_settings'	=> 'PayNow Checkout requires an API key, store ID, and default product ID.',
'xpaynowcheckout_missing_api_credentials'	=> 'API key and store ID are required.',
'xpaynowcheckout_invalid_api_credentials'	=> 'Could not validate PayNow API credentials. Please check your API key and store ID.',
'xpaynowcheckout_missing_order_id'	=> 'Cannot refund: no PayNow order ID found on transaction.',
'xpaynowcheckout_payment_cancelled'	=> 'Payment was cancelled.',

/* XENNTEC ACP Tab */
'menutab__xenntec'	=> 'XENNTEC Apps',
'menutab__xenntec_icon'	=> 'times',

/* XENNTEC License */
'__app_xpaynowcheckout_license'	=> 'License',
'xenntec_license_key'	=> 'License Key',
'xenntec_license_status'	=> 'Status',
'xenntec_license_domain'	=> 'Licensed Domain',
'xenntec_license_expiry'	=> 'Expiry',
'xenntec_license_perpetual'	=> 'Perpetual',
'xenntec_license_last_checked'	=> 'Last Checked',
'xenntec_license_recheck'	=> 'Re-check Now',
'xenntec_license_activate'	=> 'Activate',
'xenntec_license_change'	=> 'Change',
'xenntec_license_buy'	=> 'Buy License',
'xenntec_lic_key_required'	=> 'A license key is required.',
'xenntec_lic_activated_ok'	=> 'License activated successfully.',
'xenntec_lic_activated_fail'	=> 'License activation failed. Please check your key and try again.',
'xenntec_lic_rechecked'	=> 'License re-checked.',
'xenntec_lic_status_valid'	=> 'Valid',
'xenntec_lic_status_expiring'	=> 'Expiring Soon',
'xenntec_lic_status_grace'	=> 'Grace Period',
'xenntec_lic_status_expired'	=> 'Expired',
'xenntec_lic_status_invalid'	=> 'Invalid',
'xenntec_lic_status_missing'	=> 'No license key entered. Please activate your license.',
'xenntec_lic_notice_expiring'	=> 'Your license expires in %d day(s). Please renew soon.',
'xenntec_lic_notice_grace'	=> 'License server unreachable. Operating in grace period until %s.',
'xenntec_lic_notice_expired'	=> 'Your license has expired. Please renew to continue receiving updates.',
'xenntec_lic_notice_invalid'	=> 'Your license key is invalid. Please check your key or contact support.',

);
