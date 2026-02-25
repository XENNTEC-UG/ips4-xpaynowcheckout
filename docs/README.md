# X PayNow Checkout

PayNow.gg payment gateway for IPS Nexus. Provides hosted checkout redirect, webhook-driven reconciliation (base64 HMAC-SHA256 signature verification with multi-secret iteration), refund processing, chargeback handling with auto-ban and benefit revocation, and full operational tooling (integrity panel, forensics viewer, webhook replay). Uses stateless inline products per transaction (no mapping table). Sibling architecture to `xstripecheckout` (Stripe) and `xpolarcheckout` (Polar).

Phases 1–4 complete (core checkout, refund & chargeback, monitoring & resilience, invoice settlement UX, IPS coupon forwarding). Stripe UX parity achieved. Phase 5 (subscription support) deferred.

## Read Order

1. [GitHub Issues](https://github.com/XENNTEC-UG/ips4-xpaynowcheckout/issues) — open work items
2. [ARCHITECTURE.md](ARCHITECTURE.md) — architecture, PayNow API contracts, data model
3. [FEATURES.MD](FEATURES.MD) — capability overview
4. [FLOW.md](FLOW.md) — runtime flows and end-to-end diagrams
5. [TEST_RUNTIME.md](TEST_RUNTIME.md) — manual verification checklist

## Source Paths

- Gateway: `app-source/sources/XPaynowCheckout/XPaynowCheckout.php`
- Webhook controller: `app-source/modules/front/webhook/webhook.php`
- Integrity ACP module: `app-source/modules/admin/monitoring/integrity.php`
- Forensics ACP module: `app-source/modules/admin/monitoring/forensics.php`
- Forensics schema: `app-source/data/schema.json` (`pnc_webhook_forensics`)
- Language file: `app-source/dev/lang.php`

## Source of Truth

- App code: `ips-dev-source/apps/xpaynowcheckout/app-source/`
- Runtime copy: `data/ips/applications/xpaynowcheckout/` (synced via `ips-dev-sync.ps1`)

## Global Context

- [../../../../README.md](../../../../README.md)
- [../../../../IPS4_DEV_GUIDE.md](../../../../IPS4_DEV_GUIDE.md)
- [../../../../AI_TOOLS.md](../../../../AI_TOOLS.md)
- [../../../../CLAUDE.md](../../../../CLAUDE.md)
