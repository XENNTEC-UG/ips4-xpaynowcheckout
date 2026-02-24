# X PayNow Checkout Features

> PayNow.gg payment gateway for IPS Nexus focused on hosted checkout, webhook reconciliation, and operational visibility.

## Version Snapshot

| Package version | Long version | Source |
| --- | --- | --- |
| `1.0.6` | `10006` | `app-source/data/versions.json` |

## Product Positioning

`xpaynowcheckout` provides a maintainable IPS-native payment layer around PayNow.gg hosted checkout and webhook-driven reconciliation. It mirrors the architecture of `xstripecheckout` (Stripe) and `xpolarcheckout` (Polar) for consistent operational workflows across all payment providers.

## Feature Pillars

| Pillar | What it enables | Business impact |
| --- | --- | --- |
| Hosted checkout orchestration | Redirect-based checkout start from Nexus transactions | Faster customer payment flow with reduced PCI exposure |
| Webhook-driven reconciliation | Event handling maps PayNow state to IPS transaction state | Consistent finance records between provider and IPS |
| Idempotent processing | Signature checks, replay protection, event tracking | Safer handling of duplicate or delayed deliveries |
| Settlement evidence snapshots | Durable capture of order/payment evidence in transaction metadata | Better support and accounting diagnostics |
| Integrity operations center | ACP integrity panel, replay controls, forensics visibility | Faster incident detection and response |
| Recovery tooling | Scheduled/manual replay paths with telemetry | Lower revenue risk during webhook outages |
| Chargeback protection | Auto-ban on chargeback, resolution logging | Reduced fraud exposure and faster response |
| Rich invoice view | Settlement display with charge summary, payment refs, status badges | Customer-facing transparency matching Stripe-level detail |
| IPS coupon forwarding | Forward IPS coupons as PayNow one-time coupons | Proper discount breakdown on hosted checkout |
| Multi-gateway payment support | PayNow aggregates Stripe, PayPal, crypto, and regional methods | Broader customer payment coverage via single integration |

## Capability Status

### Scaffold + Audit Fixes (v1.0.0–v1.0.1 — DONE)
- Gateway app scaffold, Application.php, and all IPS4 config files.
- Gateway class with settings form (API key, store ID, webhook config, chargeback toggle, replay settings).
- Webhook controller with base64 HMAC-SHA256 signature verification, multi-secret iteration, and forensic logging (with event_id).
- Webhook payload parsing: `event_type` / `event_id` / `body` structure (verified against PayNow docs).
- Event dispatch: SCREAMING_SNAKE_CASE switch matching incoming webhooks; PascalCase for management API.
- 6 hooks: gateway registration, coupon naming, invoice view (on `view()`), settlement themes (x2), member profile.
- Integrity panel ACP module with manage(), replay(), ackErrors() action stubs.
- Forensics viewer ACP module with Table\Db, filters, and per-row delete.
- Replay task skeleton with configurable lookback, overlap, and max events.
- Integrity monitor task with alert condition checking and 90-day forensics pruning.
- AdminNotification extension + MemberACPProfileBlocks extension (stubs).
- Template stubs for settlement cards (front-end + print), integrity panel, and member summary.
- Language file with 100+ keys covering all features.
- DB table `pnc_webhook_forensics` with indexes.
- App installed and enabled in dev environment.

### Phase 1 — Core Checkout (DONE in v1.0.2, enhanced v1.0.6)
- `auth()` — Hosted checkout session creation, redirect to PayNow checkout URL, gw_id correlation.
- **Dynamic inline products** (v1.0.6) — `buildPaynowLineItems()` iterates invoice items, creates `inline_product` per line with actual IPS product name and price. Matches Stripe's `product_data` pattern (stateless, no mapping table).
- `testSettings()` — API key validation, auto-create multi-secret webhook subscriptions, webhook URL generation.
- `OnOrderCompleted` webhook handler — Transaction resolution (3-level fallback), settlement snapshot, `checkFraudRulesAndCapture()`.
- Customer resolution — Create/lookup PayNow customer via `cm_profiles` caching.
- Settlement snapshot persistence in `t_extra` and `i_status_extra` with mismatch detection.
- Gateway settings: `default_product_id` optional fallback, `checkValidity()` requires only API key + store ID.

### Phase 2 — Refund & Chargeback (TODO)
- `refund()` — Call PayNow order refund API.
- `OnRefund` webhook handler.
- `OnChargeback` + `OnChargebackClosed` handlers.
- Chargeback ban logic.
- Member profile block (chargeback/refund counts).

### Phase 3 — Monitoring & Resilience (TODO)
- Integrity panel implementation with health cards, mismatch table, replay status.
- Forensics viewer with full filter/delete capability.
- Webhook replay via PayNow `/webhooks/{id}/history` API.
- AdminNotification alert conditions.
- Error acknowledge button on integrity panel.

### Phase 4 — Polish (TODO)
- Invoice view settlement card (theme hooks with full data rendering).
- Print invoice settlement card.
- IPS coupon forwarding (create PayNow coupon for IPS discounts).
- Currency validation in `checkValidity()`.

### Phase 5 — Subscription Support (Future)
- `OnSubscriptionActivated` / `OnSubscriptionRenewed` / `OnSubscriptionCanceled` handlers.
- Recurring billing support in `auth()` with line-level `subscription: true`.

## Audit-Backed Parity Backlog (2026-02-24)

Full audit log: `docs/ARCHITECTURE.md` section 15.

### API correctness gaps (all DONE in v1.0.1)
- ~~Normalize incoming webhook payload handling~~ — DONE (event_type/event_id/body)
- ~~Normalize incoming webhook event names~~ — DONE (SCREAMING_SNAKE_CASE dispatch)
- ~~Align webhook signature verification~~ — DONE (base64 HMAC-SHA256)
- ~~Ensure forensics logging persists event_id~~ — DONE

### Stripe-equivalent UX/features to carry into PayNow
- ~~Invoice hook parity: run on invoice `view()` path~~ — DONE
- Invoice hook parity: add two-column layout with provider settlement summary — TODO
- Invoice hook parity: append Payment & References section — TODO
- Settlement parity: subtotal, discount, net subtotal, tax, and total formatting — TODO
- Settlement parity: mismatch and tax-difference explanation states — TODO
- Settlement parity: provider IDs and customer-facing invoice/receipt links — TODO
- Print parity: implement print settlement view — TODO
- Monitoring parity: integrity cards/tables with endpoint drift visibility — TODO
- Monitoring parity: replay controls including dry-run behavior — TODO
- ~~Hook parity: add `couponNameHook`~~ — DONE

## Capability Highlights

### Payment flow and gateway behavior
- Creates PayNow hosted checkout sessions from Nexus transactions with redirect-based flow.
- PayNow aggregates multiple payment methods (Stripe, PayPal, crypto, regional gateways) — customers see all available options on the hosted checkout page.
- Currency-aware minor-unit conversion using IPS `\IPS\nexus\Money::numberOfDecimalsForCurrency()` and `\IPS\Math\Number`.
- Metadata passthrough (ips_transaction_id, ips_invoice_id, ips_member_id) for reliable transaction resolution.
- Order-level refunds only (`SUPPORTS_PARTIAL_REFUNDS = FALSE`).

### Webhook reliability and safety
- Base64 HMAC-SHA256 signature verification with `PayNow-Signature` + `PayNow-Timestamp` headers, multi-secret iteration.
- Timestamp freshness enforcement (300-second tolerance, millisecond timestamps from PayNow).
- Event-id-based idempotency — duplicate deliveries return 200 without state mutation.
- Validation failures persisted to `pnc_webhook_forensics` for ACP security audit with 90-day auto-cleanup.

### Settlement and reconciliation
- Normalized settlement snapshot stored in transaction `t_extra` and invoice `i_status_extra`.
- Provider-vs-IPS total comparison with mismatch detection flags.
- Subtotal, tax, discount, and total display fields from PayNow order model.
- Human-readable order reference (`pretty_id`: `pn-XXXXX`).

### Monitoring and recovery
- ACP integrity panel with webhook health indicators, replay status, and mismatch review.
- Replay pipeline sourced from PayNow webhook delivery history API.
- Integrity monitor task (5-min interval) for automated health checks and forensics pruning.
- Admin notification extension surfaces persistent payment integrity problems to ACP users.
- ACP forensics viewer for webhook failure audit trail.

## Built-In Guardrails

- Fail-safe webhook behavior prioritizing consistent transaction state.
- Strict URL and payload validation before persistence.
- Centralized event-type constants and replay bounds.
- ACP-safe error logging with forensic traceability.

## Ideal Use Cases

- IPS stores using PayNow.gg for game server monetization (game keys, subscriptions, digital goods).
- Stores wanting aggregated payment methods (Stripe + PayPal + crypto) via a single gateway integration.
- Admin teams that need webhook reliability guarantees, replay recovery, and forensic audit trails.
- Teams running multiple payment gateways (Stripe + Polar + PayNow) with consistent ACP monitoring workflows.
