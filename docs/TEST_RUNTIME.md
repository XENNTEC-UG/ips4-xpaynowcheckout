# X PayNow Checkout Runtime Verification

Current runtime baseline is `v1.0.18` / `10018`.

## Environment Preconditions

1. App `xpaynowcheckout` is installed and enabled in `core_applications`.
2. Gateway exists in ACP payment methods (Nexus > Settings > Payment Methods).
3. Gateway settings contain valid PayNow API key, store ID, and webhook secret.
4. Webhook endpoint is reachable from PayNow servers (or local tunnel active for dev).

## Pre-Implementation Smoke Checks

These checks validate the skeleton is correctly installed before any TODO stubs are filled in.

### 1. Install/Registration Integrity
- [ ] App enables cleanly (no schema errors).
- [ ] Module `admin/monitoring` registered in `core_modules`.
- [ ] Module `front/webhook` registered in `core_modules`.
- [ ] Gateway appears in ACP payment method selection (`X PayNow Checkout`).
- [ ] No schema errors for `pnc_webhook_forensics` table.
- [ ] Hooks registered in `core_hooks` (6 entries: code_GatewayModel, theme_pnc_clients_settle, theme_pnc_print_settle, code_memberProfileTab, invoiceViewHook, couponNameHook).
- [ ] Tasks registered in `core_tasks` (2 entries: `pncWebhookReplay`, `pncIntegrityMonitor`).
- [ ] Extensions discovered via `data/extensions.json`.

### 2. ACP Navigation
- [ ] Integrity panel accessible at `admin/?app=xpaynowcheckout&module=monitoring&controller=integrity`.
- [ ] Forensics viewer accessible at `admin/?app=xpaynowcheckout&module=monitoring&controller=forensics`.
- [ ] ACP menu entries appear under XENNTEC Apps tab (accordion sidebar).

### 3. Gateway Settings Form
- [ ] API key field renders.
- [ ] Store ID field renders.
- [ ] Webhook URL display field renders.
- [ ] Webhook secret display field renders.
- [ ] Chargeback ban toggle renders (default: ON).
- [ ] Replay settings render (lookback, overlap, max events).

## Post-Phase-1 Smoke Checks (After Core Checkout Implementation)

### 4. Signature Validation
- [ ] Missing signature headers → HTTP `401` + forensic row.
- [ ] Invalid signature → HTTP `401` + forensic row.
- [ ] Stale timestamp (>300s) → HTTP `401` + forensic row (failure_reason=`timestamp_too_old`).
- [ ] Valid signature → event processing (HTTP `200`).

### 5. Checkout Flow
- [ ] `auth()` creates PayNow checkout session via API.
- [ ] Customer redirected to PayNow hosted checkout URL.
- [ ] `return_url` redirects back to IPS after payment.
- [ ] `cancel_url` redirects back to IPS on cancel.

### 6. Event-Map Transitions
- [ ] `OnOrderCompleted` → transaction approved, `markPaid()` triggers.
- [ ] Settlement snapshot stored in `t_extra` with expected keys.
- [ ] `t_gw_id` set to PayNow order ID.

### 7. Idempotency
- [ ] Re-delivering the same event payload returns HTTP `200` without state mutation.

### 8. Snapshot Persistence
- [ ] `xpaynowcheckout_snapshot` written to `t_extra`.
- [ ] Snapshot includes: `paynow_order_id`, `paynow_pretty_id`, `subtotal_minor`, `tax_minor`, `total_minor`, `total_display`, `billing_name`, `billing_email`, `billing_country`, `has_total_mismatch`, `total_difference_tax_explained`, `captured_at`, `captured_at_iso`.

## Post-Phase-2 Checks (After Refund & Chargeback)

### 9. Refund
- [ ] `refund()` calls PayNow order refund API.
- [ ] `OnRefund` webhook updates transaction status.
- [ ] Settlement snapshot updated with refund data.

### 10. Chargeback
- [ ] `OnChargeback` webhook detected and logged.
- [ ] If `chargeback_ban` enabled → member banned.
- [ ] `OnChargebackClosed` webhook logs resolution.

## Post-Phase-3 Checks (After Monitoring & Resilience)

### 11. Integrity Panel
- [ ] Webhook health cards show error counts (1h/24h).
- [ ] Replay status shows last run time and event count.
- [ ] Mismatch table lists transactions with `has_total_mismatch = true`.
- [ ] "Run Replay Now" button triggers manual replay.
- [ ] "Acknowledge Errors" button clears stale alerts.

### 12. Forensics Viewer
- [ ] Table displays forensic rows with correct columns.
- [ ] Filter by failure reason works.
- [ ] Filter by event type works.
- [ ] Per-row delete works with CSRF protection.

### 13. Replay Task
- [ ] Task fetches failed deliveries from PayNow webhook history API.
- [ ] Deduplication prevents reprocessing already-handled events.
- [ ] Replay cursor state persisted after each run.

### 14. Integrity Monitor
- [ ] Task runs every 5 minutes.
- [ ] Raises admin notification when error thresholds exceeded.
- [ ] Prunes forensics rows older than 90 days.

## Post-Phase-4 Checks (After Polish)

### 15. Invoice View
- [ ] Settlement card renders on front-end invoice view.
- [ ] Settlement card renders on print invoice view.
- [ ] Shows: subtotal, tax, discount, total, PayNow order ref, payment method.

### 16. Coupon Forwarding
- [ ] IPS Nexus coupon creates one-time PayNow coupon.
- [ ] Coupon attached to checkout session.
- [ ] PayNow hosted checkout shows discount breakdown.

## Automated Checks (To Be Executed)

- Gateway registration runtime check (`XPaynowCheckout` appears in gateway map).
- Replay task dry-run execution check.
- Signature response checks (`missing`, `invalid`, `stale`).
- PayNow sandbox API contract checks (checkout session creation, order fetch).
- ACP module load checks (integrity, forensics).
