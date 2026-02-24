# X PayNow Checkout App - Changelog

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
