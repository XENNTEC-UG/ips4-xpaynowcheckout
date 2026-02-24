# X PayNow Checkout

## Current Status

`xpaynowcheckout` v1.0.1 — scaffold + audit corrections applied. Installed and enabled in dev environment. Next: Phase 1 (core checkout flow).

**Done** (v1.0.0–v1.0.1):
- App scaffold with all config files, Application.php, schema.json.
- Gateway class with settings form. Webhook controller with base64 HMAC-SHA256 signature verification, multi-secret iteration, forensic logging.
- 6 hooks: gateway registration, coupon naming, invoice view, settlement themes (x2), member profile.
- ACP modules (integrity + forensics), tasks, extensions, templates — all stubbed with TODO markers.
- Language file with 100+ keys. DB table `pnc_webhook_forensics`.

**Next** (Phase 1 — Core Checkout):
- `auth()`, `testSettings()`, `OnOrderCompleted` handler, customer resolution, settlement snapshots.

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
