# X PayNow Checkout — PayNow.gg Payment Gateway for IPS4 / Invision Community

A payment gateway that integrates [PayNow.gg](https://paynow.gg) with [IPS4 / Invision Community](https://invisioncommunity.com) Nexus Commerce. Accept payments through PayNow's hosted checkout with full webhook reconciliation, settlement tracking, chargeback protection, and admin tooling — built to the same standard as Stripe and Polar gateway integrations.

---

## Why PayNow + IPS4?

[PayNow.gg](https://paynow.gg) is a payment platform popular with game server communities, offering hosted checkout that aggregates Stripe, PayPal, cryptocurrency, and regional payment methods into a single integration. Until now, IPS4 Nexus had no native PayNow integration. This app bridges that gap with a production-grade gateway that handles the full payment lifecycle:

- **Checkout** — Redirect customers to PayNow's hosted checkout from any Nexus invoice
- **Reconciliation** — Webhook-driven state sync keeps IPS transactions accurate
- **Refunds** — Process refunds through the Nexus admin interface
- **Chargebacks** — Automatic member ban and benefit revocation on chargeback, with resolution tracking
- **Monitoring** — ACP integrity panel, forensics viewer, and automated replay recovery

## Features

### Payment Flow
- Hosted checkout redirect from Nexus invoices (no PCI exposure)
- Currency-aware amount conversion (supports all decimal configurations)
- Dynamic inline products — automatically creates product entries per transaction matching your Nexus items
- Multi-payment-method support — customers see Stripe, PayPal, crypto, and regional options on a single checkout page
- IPS coupon forwarding to PayNow as one-time discounts with math verification

### Webhook Processing
- Base64 HMAC-SHA256 signature verification with multi-secret iteration
- Timestamp freshness enforcement (300-second tolerance)
- Event-ID idempotency — safe against duplicate deliveries
- Terminal-state guardrails prevent accidental status regression
- Failed webhook forensics persisted for admin audit (90-day retention)

### Chargeback Protection
- Automatic member ban on chargeback with benefit revocation
- Chargeback resolution tracking (won/lost) with status restore
- ACP member profile block showing chargeback and refund history

### Settlement & Invoicing
- Normalized settlement snapshots stored on every transaction
- Provider-vs-IPS total comparison with mismatch detection
- Human-readable order references (`pn-XXXXX`)
- Two-column invoice view with charge summary, payment references, and status badges

### Admin Control Panel (ACP)
- Integrity panel with webhook health indicators and status cards
- Webhook replay pipeline with deduplication and dry-run mode
- Configurable replay guardrails (lookback window, overlap, max events per run)
- Forensics viewer for webhook failure audit trail
- Admin notifications for persistent payment integrity issues

## Requirements

| Requirement | Version |
|---|---|
| IPS4 / Invision Community | 4.7+ |
| PHP | 8.1+ |
| Nexus Commerce | Included with IPS4 |
| PayNow.gg Account | [paynow.gg](https://paynow.gg) |

## Installation

### 1. Download

Download the latest release from the [Releases](https://github.com/XENNTEC-UG/ips4-xpaynowcheckout/releases) page, or clone the repo:

```bash
git clone https://github.com/XENNTEC-UG/ips4-xpaynowcheckout.git
```

The IPS4 application files are in the `app-source/` directory.

### 2. Upload to IPS4

Copy the contents of `app-source/` into your IPS4 installation:

```
app-source/ --> /applications/xpaynowcheckout/
```

### 3. Install via ACP

1. Go to **AdminCP > System > Applications**
2. The app will appear as "X PayNow Checkout" — click **Install**
3. Navigate to **AdminCP > Commerce > Payment Methods**
4. Add a new payment method and select **X PayNow Checkout**

### 4. Configure Gateway Settings

You'll need from your PayNow dashboard:
- **API Key** — from your store settings
- **Store ID** — your PayNow store identifier
- **Webhook Secret** — from your webhook configuration

Enter these in the gateway settings form and save. The gateway will automatically create webhook subscriptions for all supported event types.

## Architecture

```
Customer  -->  Nexus Invoice  -->  PayNow Hosted Checkout
                                         |
                                    (customer pays)
                                         |
                                   PayNow Webhook
                                         |
                              Signature Verification
                                         |
                              Event Mapping & State Sync
                                         |
                         IPS Transaction Updated (paid/refunded)
                                         |
                         Settlement Snapshot Persisted
```

**Key design decisions:**
- Webhook-first reconciliation (no polling)
- Idempotent event processing with terminal-state guardrails
- Fail-safe behavior — webhook failures never corrupt transaction state
- Settlement snapshots provide full audit trail independent of PayNow dashboard access
- Sibling architecture to xstripecheckout (Stripe) and xpolarcheckout (Polar) for consistent operational workflows

## File Structure

```
app-source/
  data/             Application metadata, schema, hooks, extensions
  dev/              Language strings
  hooks/            Gateway registration, coupon naming, invoice view, settlement display
  modules/
    admin/          ACP integrity panel, forensics viewer
    front/          Webhook endpoint
  sources/          Gateway class (XPaynowCheckout.php)
  tasks/            Webhook replay, integrity monitor
  extensions/       Admin notifications, member profile blocks
docs/               Documentation and runbooks
```

## Documentation

| Document | Description |
|---|---|
| [FEATURES.MD](docs/FEATURES.MD) | Complete feature list and capability status |
| [ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture reference, data model, API contracts |
| [FLOW.md](docs/FLOW.md) | End-to-end payment flow and webhook invariants |
| [TEST_RUNTIME.md](docs/TEST_RUNTIME.md) | Runtime testing procedures |
| [Releases](https://github.com/XENNTEC-UG/ips4-xpaynowcheckout/releases) | Version history and release notes |

## Compatibility

This app is designed to coexist with other Nexus payment gateways. When multiple XENNTEC checkout apps are installed (Stripe, Polar, PayNow), an idempotency guard prevents double-processing of shared UI enhancements (invoice view, order details, settlement display).

## Contributing

Contributions are welcome. Please open an issue first to discuss what you'd like to change.

## License

This project is free to use. See the repository for license details.

## Links

- [PayNow.gg](https://paynow.gg) — Payment platform for game communities
- [PayNow Documentation](https://docs.paynow.gg) — API reference and guides
- [IPS4 / Invision Community](https://invisioncommunity.com) — Community platform
- [XENNTEC](https://xenntec.com) — Developer

---

**Made by [XENNTEC](https://github.com/XENNTEC-UG)**
