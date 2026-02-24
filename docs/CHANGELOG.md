# X PayNow Checkout App - Changelog

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
