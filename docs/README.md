# X PayNow Checkout

## Current Status

`xpaynowcheckout` is a skeleton IPS4 app ready for Codex/developer implementation.

- App scaffold and all config files (application.json, modules.json, hooks.json, etc.) are in place.
- Gateway class, webhook controller, integrity panel, forensics viewer, tasks, hooks, and extensions are stubbed with TODO markers.
- PayNow HMAC-SHA256 webhook signature verification is implemented.
- Settlement snapshot schema and forensics table are defined.
- Language file contains all required keys for gateway settings, webhook handling, ACP panels, and alerts.
- Template stubs exist for settlement cards (front-end, print), integrity panel, and member profile block.

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
