# X PayNow Checkout App - Changelog

## 2026-02-25 - v1.0.8: Phase 2 — Refund & Chargeback

### Refund
- Implemented `refund()` gateway method: calls `POST /v1/stores/{storeId}/orders/{orderId}/refund`.
  PayNow only supports full order refunds (no partial). Logs warning if partial amount requested.
- Implemented `handleRefund()` webhook handler (ON_REFUND): resolves transaction, validates terminal
  refund status (`completed`/`approved`), stores refund metadata in `t_extra`, sets STATUS_REFUNDED.
  Non-terminal statuses (`created`/`processing`/`canceled`/`failed`) are logged and skipped.

### Chargeback
- Implemented `handleChargeback()` webhook handler (ON_CHARGEBACK): resolves transaction, stores
  chargeback metadata (order_id, amount, status, reason, event_id), sets STATUS_DISPUTED.
  If `chargeback_ban` enabled (default TRUE), permanently bans member (`temp_ban = -1`) with
  history log entry. Revokes benefits via `markUnpaid(STATUS_CANCELED)`. Sends admin notification.
- Implemented `handleChargebackClosed()` webhook handler (ON_CHARGEBACK_CLOSED): resolves
  transaction, updates chargeback metadata with resolution/closed_at. Won → STATUS_PAID + markPaid()
  if invoice balance is zero. Lost → STATUS_REFUNDED. Unknown → metadata only, no status change.

### ACP Member Profile Block
- Implemented `PaynowPaymentSummary` profile block: queries PayNow gateway transactions for disputes
  and refunds. Shows chargebacks count (warning badge), latest chargeback detail with reason and date,
  refunds count, ban status, and link to integrity panel. Returns NULL (hidden) when no disputes/refunds.

### Code Quality
- Extracted `resolveTransactionFromWebhook()` helper in webhook controller: 3-level fallback
  (metadata → nested checkout metadata → DB gw_id lookup by order_id/checkout_id/id). Refactored
  `handleOrderCompleted()` and all new handlers to use this shared helper.

### Language
- Added 3 new lang keys: `xpaynowcheckout_dispute_closed_won`, `xpaynowcheckout_dispute_closed_lost`,
  `xpaynowcheckout_missing_order_id`.

## 2026-02-24 - v1.0.7: Fix multi-item checkout slug collision

### Bug Fix
- Fixed PayNow API "product slug is already in use" error when checking out with multiple items
  or when re-purchasing the same products across different checkout sessions.
- Added unique `slug` field (`ips-t{transactionId}-i{itemIndex}`) to each `inline_product` in
  `buildPaynowLineItems()`, preventing auto-generated slug collisions from product names.
- Multi-item checkout verified: 2x Starter 7D + 1x Premium 30D displays correctly on PayNow
  hosted checkout with individual line items, quantities, and prices.

## 2026-02-24 - v1.0.6: Dynamic inline product line items

### Feature
- Replaced static `default_product_id` usage with dynamic `inline_product` line items in `auth()`.
  Each IPS invoice item now becomes a PayNow checkout line with the actual product name and price,
  matching the Stripe gateway's inline `product_data` pattern.
- New `buildPaynowLineItems()` method iterates `$transaction->invoice->items`, builds inline product
  definitions with name, description, price (minor units), and quantity per line item.
- Fallback: if no invoice items resolve, a single summary line with the invoice total is used.
- `default_product_id` is now optional (no longer required in settings or `checkValidity()`).
- Updated language description for the Default Product ID setting to reflect optional status.

## 2026-02-24 - v1.0.5: Fix webhook body parsing

### Bug Fix
- Fixed PHP operator precedence bug in webhook controller: `isset() AND is_array()` with ternary
  evaluated to boolean instead of array due to `AND` vs `&&` precedence. Changed to `&&` with
  explicit parentheses. This caused a TypeError crash on every incoming webhook.

## 2026-02-24 - v1.0.4: Fix gateway settings fields

### Bug Fixes
- Changed webhook URL from disabled Text to editable Url field so admins can set the external tunnel URL.
- Changed webhook secret from disabled to required editable Text field so admins can paste the signing secret from PayNow dashboard.

## 2026-02-24 - v1.0.3: Fix gateway registration hook

### Bug Fix
- Fixed `code_GatewayModel` hook: renamed method from `gatewayClasses()` to `gateways()` to match `\IPS\nexus\Gateway` parent method signature. This caused PayNow Checkout to not appear in the ACP gateway selection list.
- Aligned error handling pattern with xstripecheckout/xpolarcheckout siblings (DEBUG_HOOKS logging, parent fallback).

## 2026-02-24 - v1.0.2: Phase 1 — Core Checkout Flow

### Core Checkout (`auth()`)
- Implemented hosted checkout session creation via `POST /v1/stores/{storeId}/checkouts`.
- Builds checkout payload with customer ID, product line, metadata (ips_transaction_id, ips_invoice_id, ips_member_id, gateway_id).
- Stores PayNow checkout session ID as `gw_id` for webhook correlation.
- Redirects customer to PayNow hosted checkout URL.
- Supports custom return/cancel URLs from gateway settings with IPS transaction URL fallback.

### Gateway Validation (`testSettings()`)
- Validates API credentials by fetching products from PayNow API.
- Auto-generates webhook URL from IPS internal routing.
- Creates multi-secret webhook subscriptions (one POST per event type in REQUIRED_WEBHOOK_EVENTS).
- Stores all signing secrets in `webhook_secrets` array for signature verification.
- Normalizes and bounds all settings (replay lookback/overlap/max events).

### Customer Resolution (`getPaynowCustomer()`)
- Creates PayNow customer via `POST /v1/stores/{storeId}/customers` with IPS member name + metadata.
- Caches PayNow customer ID in `cm_profiles[gateway_id]` for future lookups.
- Skips API call on subsequent checkouts when cached ID exists.

### Webhook Handler (`handleOrderCompleted`)
- Resolves IPS transaction from webhook metadata with three-level fallback: direct metadata → nested checkout metadata → gw_id DB lookup by checkout_id.
- Idempotency: skips if transaction already in paid/refunded state.
- Builds normalized settlement snapshot with subtotal/tax/discount/total (minor + display), billing info, IPS-vs-PayNow total comparison with mismatch detection and tax-explains-difference flag.
- Persists snapshot to both `t_extra` and `i_status_extra`.
- Updates `gw_id` from checkout session ID to PayNow order ID.
- Approves transaction via `checkFraudRulesAndCapture()` with optional MaxMind integration.

### Gateway Settings
- Added `default_product_id` required field (PayNow product ID for checkout line items).
- `checkValidity()` now blocks transactions when required settings are missing.

### Language
- Added 7 new language keys for default product ID, error messages.

## 2026-02-24 - v1.0.1: Audit Corrections + Parity Fixes

### Critical Fixes
- Fixed webhook signature verification: changed from hex to base64 HMAC-SHA256 output to match PayNow's documented format.
- Fixed webhook payload parsing: changed from `$payload['event']`/`$payload['id']` to correct `event_type`/`event_id`/`body` structure.
- Fixed event name casing in processEvent() switch: PascalCase → SCREAMING_SNAKE_CASE (`ON_ORDER_COMPLETED`, `ON_REFUND`, etc.).

### Medium Fixes
- Fixed invoice hook: changed from `manage()` to `view()` to match xstripecheckout/xpolarcheckout sibling pattern.
- Added `couponNameHook` on `\IPS\nexus\Coupon` for consistent "Coupon: CODE" display naming. Registered in `hooks.json`.

### Low Fixes
- Fixed forensics logging: `logForensic()` now accepts and persists `event_id` parameter. All call sites pass event ID when available.

### Webhook Architecture
- Added multi-secret support: PayNow uses one webhook subscription per event, each with its own signing secret. Webhook handler now iterates all stored secrets for verification. Settings support `webhook_secrets` array.

### Architecture Docs
- Updated `docs/ARCHITECTURE.md` sections 15.1-15.5 with verified findings, rejected false positive, additional discoveries (multi-secret model, platform-identity customers), and resolved status.

## 2026-02-24 - Documentation: Audit Findings Logged

### Audit Log Added
- Added `docs/AUDIT_FINDINGS_2026-02-24.md` as the canonical audit handoff file for implementation/review continuity.
- Logged confirmed PayNow API corrections (webhook payload shape, event naming normalization, signature format considerations, endpoint confirmations).
- Logged IPS4 standards/compliance findings and validation constraints observed during audit.

### Architecture and Feature Docs Updated
- Updated `docs/ARCHITECTURE.md` with a dedicated audit section:
  - implementation corrections to apply
  - Stripe-parity requirements for invoice/settlement/monitoring UX
  - confirmed skeleton gaps
- Updated `docs/FEATURES.MD` with an explicit audit-backed parity backlog so PayNow implementation tracks Stripe feature parity.

## 2026-02-24 - v1.0.0: Skeleton App Structure

### App Scaffold
- Created full IPS4 app scaffold with all required config files: `application.json`, `versions.json`, `modules.json`, `hooks.json`, `extensions.json`, `tasks.json`, `schema.json`, `acpmenu.json`, `acprestrictions.json`, `settings.json`, `acpsearch.json`, `widgets.json`, `themesettings.json`.
- Gateway class `XPaynowCheckout` extending `\IPS\nexus\Gateway` with complete settings form, webhook signature verification, alert stats collection, and TODO-marked stubs for `auth()`, `refund()`, `testSettings()`.
- Webhook controller with full HMAC-SHA256 signature verification pipeline, event dispatcher, forensic logging, and TODO-marked handler stubs for all 7 event types.
- 5 hooks: `code_GatewayModel` (gateway registration), `theme_pnc_clients_settle` + `theme_pnc_print_settle` (settlement rendering), `code_memberProfileTab` (ACP member profile), `invoiceViewHook` (invoice settlement data injection).
- 2 tasks: `pncWebhookReplay` (15-min replay pipeline), `pncIntegrityMonitor` (5-min health check + forensics pruning).
- 2 extensions: `PaymentIntegrity` (AdminNotifications), `PaynowPaymentSummary` (MemberACPProfileBlocks).
- ACP modules: `monitoring/integrity` (integrity panel) and `monitoring/forensics` (forensics viewer) with action stubs.
- DB table: `pnc_webhook_forensics` with forensic_id, event_type, event_id, failure_reason, ip_address, http_status, payload_snippet, created_at columns.
- Language file with 100+ keys for all features.
- Template stubs for settlement cards (front-end + print), integrity panel, and member payment summary.

### Documentation
- `ARCHITECTURE.md` — Comprehensive architecture reference including PayNow API details, app structure, payment flow diagrams, settlement snapshot schema, implementation phases, and Stripe/Polar comparison.
- `README.md`, `FEATURES.MD`, `FLOW.md`, `CHANGELOG.md`, `TEST_RUNTIME.md` — Full documentation suite.
