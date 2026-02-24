# X PayNow Checkout Audit Findings (2026-02-24)

This file logs the full audit pass requested for `xpaynowcheckout` so implementation and review can continue without re-discovery.

## Scope Covered

- Project routing and coding standards:
  - `CLAUDE.md`
  - `IPS4_DEV_GUIDE.md`
- App under review:
  - All files under `ips-dev-source/apps/xpaynowcheckout/`
- Reference comparison:
  - `xstripecheckout` (gateway, webhook, monitoring, hooks, tasks, extensions, templates, configs)
  - `xpolarcheckout` (same comparison scope)
- Upstream API references:
  - `https://docs.paynow.gg/getting-started/authentication`
  - `https://docs.paynow.gg/getting-started/error-response`
  - `https://docs.paynow.gg/management/management-api/checkout`
  - `https://docs.paynow.gg/management/management-api/orders`
  - `https://docs.paynow.gg/management/management-api/payments`
  - `https://docs.paynow.gg/management/management-api/customers`
  - `https://docs.paynow.gg/management/management-api/coupons`
  - `https://docs.paynow.gg/management/management-api/subscriptions`
  - `https://docs.paynow.gg/management/management-api/webhooks`
  - `https://docs.paynow.gg/webhooks/webhooks-introduction`
  - `https://docs.paynow.gg/webhooks/validating-incoming-webhooks`
  - `https://docs.paynow.gg/webhooks/preventing-replay-attacks`
  - `https://docs.paynow.gg/webhooks/integration-implementation-examples`
  - `https://docs.paynow.gg/webhooks/webhook-events/webhooks`

## High-Confidence API Corrections

1. Incoming webhook payload shape is currently documented and coded inconsistently.
2. Management webhook subscriptions use `subscribed_to` values like `OnOrderCompleted`.
3. Incoming webhook event payload examples use `event_type`/`event_id` and uppercase event names like `ON_ORDER_COMPLETED`, `ON_REFUND`, `ON_CHARGEBACK`, `ON_CHARGEBACK_CLOSED`.
4. Webhook signature verification should use `PayNow-Timestamp` (Unix ms) + raw body, with a strict freshness window (5 minutes recommended).
5. Integration examples show base64 HMAC signatures. Current code assumes hex HMAC only, which is likely to fail in real traffic.
6. Refund endpoint remains order-level: `POST /v1/stores/{storeId}/orders/{orderId}/refund` (optional `order_line_id`).
7. Checkout endpoint in management API is documented as `POST /v1/stores/{storeId}/checkouts`.
8. Coupon endpoints are currently documented under `/coupons` (not `/discounts` for this app).

## Critical Skeleton Gaps

1. Core gateway flow is still stubbed:
   - `auth()`
   - `testSettings()`
   - `refund()`
   - customer resolution/persistence
2. Webhook business handlers are still stubs:
   - order complete
   - refund
   - chargeback open/closed
   - subscription event handling
3. Settlement snapshot normalization method for PayNow parity (`buildPaynowSnapshot` equivalent behavior) is missing.
4. Invoice hook is placeholder and currently hooks `manage()`; parity pattern in siblings uses invoice `view()` interception.
5. Front/print settlement templates are placeholder-only.
6. Integrity monitoring workflow is scaffolded but incomplete:
   - replay task logic
   - integrity panel implementation
   - admin notification lifecycle behavior

## Stripe-Parity Features Missing or Incomplete

These are the main parity targets that appear overlooked relative to `xstripecheckout` and should be mirrored into `xpaynowcheckout`.

1. Invoice view two-column enhancement model:
   - preserve core Order Details block
   - inject provider Charge Summary in right column
   - append Payment & References section
2. Charge Summary parity behavior:
   - subtotal
   - discount and net subtotal
   - tax breakdown rows when available
   - total charged with strong separator
   - mismatch warning and tax-explains-difference treatment
3. Payment & References parity behavior:
   - friendly payment method rendering
   - provider order/payment identifiers
   - captured/completed timestamp
   - invoice/receipt links when available
4. Print invoice settlement parity:
   - mirror the same settlement summary structure as front-end view
5. Hook parity:
   - `couponNameHook` is missing in `xpaynowcheckout`
   - `code_loadJs` is missing (optional for PayNow unless JS SDK flow is explicitly used)
6. Monitoring parity:
   - richer integrity panel cards/tables and endpoint drift visibility
   - replay dry-run and operational feedback loop

## IPS4 Standards / Compliance Findings

1. Hook files use try/catch but catches do not log exceptions with `\IPS\Log::log()`.
2. `invoiceViewHook` method selection likely mismatches sibling pattern (`manage()` vs `view()`).
3. Forensic logging currently inserts `event_id = NULL` instead of persisting known IDs.
4. ACP CSRF protection and typed theme hook `hookData(): array` are present and aligned.
5. Task key prefixing (`pnc*`) and DB prefix (`pnc_`) are aligned.

## Validation Notes

1. JSON config files under `app-source/data` parse successfully.
2. PHP CLI is unavailable in the current environment, so automated `php -l` could not be executed here.

## Implementation Priority for Next Pass

1. Fix API/webhook correctness first:
   - payload parsing
   - event normalization
   - signature verification compatibility
2. Implement Phase 1 checkout + order-complete webhook + snapshot persistence.
3. Implement invoice hook/template parity with Stripe-style two-column UX.
4. Implement refund/chargeback flows.
5. Complete integrity/replay/notification parity.
6. Close remaining standards issues and finalize docs/versioning.

