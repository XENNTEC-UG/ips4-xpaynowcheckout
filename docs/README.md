# X PayNow Checkout

## Current Status

`xpaynowcheckout` v1.0.8 — Phase 1 (core checkout) + Phase 2 (refund & chargeback) complete. Next: Phase 3 (monitoring & resilience).

**Done** (v1.0.0–v1.0.8):
- App scaffold with all config files, Application.php, schema.json.
- Gateway class with settings form. Webhook controller with base64 HMAC-SHA256 signature verification, multi-secret iteration, forensic logging.
- 6 hooks: gateway registration, coupon naming, invoice view, settlement themes (x2), member profile.
- ACP modules (integrity + forensics), tasks, extensions, templates — all stubbed with TODO markers.
- Language file with 100+ keys. DB table `pnc_webhook_forensics`.
- `auth()` — hosted checkout session creation + redirect.
- `buildPaynowLineItems()` — dynamic inline products per invoice item (name, price, qty, unique slug). Multi-item checkout verified.
- `testSettings()` — API validation + multi-secret webhook subscription creation.
- `getPaynowCustomer()` — platform-identity customer creation + cm_profiles caching.
- `handleOrderCompleted()` — transaction approval + settlement snapshot + mismatch detection.
- `checkValidity()` — settings enforcement (API key + store ID required, product ID optional).
- `refund()` — full order refund via PayNow API.
- `handleRefund()` — ON_REFUND webhook handler with terminal status gating.
- `handleChargeback()` — ON_CHARGEBACK handler with auto-ban, benefit revocation, admin notification.
- `handleChargebackClosed()` — ON_CHARGEBACK_CLOSED handler with won/lost resolution.
- `PaynowPaymentSummary` — ACP member profile block (chargebacks, refunds, ban status).
- `resolveTransactionFromWebhook()` — shared 3-level transaction resolution helper.

**Next** (Phase 3 — Monitoring & Resilience):
- Integrity panel implementation, forensics viewer, webhook replay, admin notifications.

## Source Paths

- Gateway: `app-source/sources/XPaynowCheckout/XPaynowCheckout.php`
- Webhook controller: `app-source/modules/front/webhook/webhook.php`
- Integrity ACP module: `app-source/modules/admin/monitoring/integrity.php`
- Forensics ACP module: `app-source/modules/admin/monitoring/forensics.php`
- Forensics schema: `app-source/data/schema.json` (`pnc_webhook_forensics`)
- Language file: `app-source/dev/lang.php`

## Doc Read Order

1. `docs/ARCHITECTURE.md` — Full architecture reference including PayNow API details
2. `docs/FEATURES.MD` — Feature pillars and capability status
3. `docs/FLOW.md` — Runtime entry points and end-to-end flow diagrams
4. `docs/CHANGELOG.md` — Version history
5. `docs/TEST_RUNTIME.md` — Runtime verification checklist

## Working Rules

- Keep active execution tracking in GitHub Issues on `XENNTEC-UG/ips4-xpaynowcheckout`.
- Log completed milestones in `docs/CHANGELOG.md` with date and version.
- Update this file if architecture entry points or status materially change.
- Follow IPS4 coding standards in `IPS4_DEV_GUIDE.md` for all code changes.
- Always import-sync before testing (`powershell -ExecutionPolicy Bypass -File .\scripts\ips-dev-sync.ps1 -Mode import`).
- Sibling references: `xstripecheckout` (Stripe gateway) and `xpolarcheckout` (Polar gateway) follow the same architectural patterns.
