# X PayNow Checkout — Architecture Document

> **Version**: 1.0.0 (skeleton)
> **App directory**: `xpaynowcheckout`
> **Task prefix**: `pnc`
> **DB table prefix**: `pnc_`
> **Datastore prefix**: `xpaynowcheckout_` / `pnc_`
> **Sibling apps**: `xstripecheckout` (Stripe), `xpolarcheckout` (Polar) — follow the same patterns

## 1. Overview

X PayNow Checkout is a Nexus payment gateway for IPS4 that integrates with the [PayNow.gg](https://docs.paynow.gg/) platform. It follows the **hosted checkout redirect** pattern established by xstripecheckout and xpolarcheckout:

1. Customer clicks "Pay" in Nexus checkout
2. Gateway creates a PayNow checkout session via API
3. Customer is redirected to PayNow-hosted checkout page (or paynow.js overlay)
4. After payment, PayNow sends webhooks to confirm order completion
5. Webhook handler approves the IPS transaction, triggering `markPaid()` on the invoice

## 2. PayNow.gg API Reference

### 2.1 Authentication
- **Base URL**: `https://api.paynow.gg/v1`
- **Header**: `Authorization: apikey {API_KEY}`
- API keys are created in the PayNow dashboard
- Case-insensitive prefix (`apikey`, `APIKEY`, `ApiKey` all valid)

### 2.2 Key Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/stores/{storeId}/checkouts` | Create checkout session |
| GET | `/stores/{storeId}/orders` | List orders (filter by status, customer, etc.) |
| GET | `/stores/{storeId}/orders/{orderId}` | Get single order |
| POST | `/stores/{storeId}/orders/{orderId}/refund` | Refund an order |
| GET | `/stores/{storeId}/payments` | List payments |
| GET | `/stores/{storeId}/payments/{paymentId}` | Get single payment |
| POST | `/stores/{storeId}/customers` | Create customer |
| GET | `/stores/{storeId}/customers/lookup` | Lookup customer by external ID |
| GET | `/stores/{storeId}/subscriptions` | List subscriptions |
| POST | `/stores/{storeId}/subscriptions/{id}/cancel` | Cancel subscription |
| GET | `/stores/{storeId}/coupons` | List coupons |
| POST | `/stores/{storeId}/coupons` | Create coupon |
| POST | `/stores/{storeId}/webhooks` | Create webhook subscription |
| GET | `/stores/{storeId}/webhooks` | List webhook subscriptions |
| POST | `/stores/{storeId}/webhooks/resend` | Resend failed webhook |
| GET | `/stores/{storeId}/webhooks/{id}/history` | Get webhook delivery history |

### 2.3 Checkout Session Creation

**POST** `/v1/stores/{storeId}/checkouts`

Request body (`CreateCheckoutSessionManagementDto`):
```json
{
  "customer_id": "flake-id",          // Required: PayNow customer ID
  "lines": [                           // Required: line items
    {
      "product_id": "flake-id",        // Existing product
      "quantity": 1,
      "subscription": false,
      "metadata": {}
    }
  ],
  "coupon_id": "flake-id",            // Optional
  "return_url": "https://...",         // Post-success redirect
  "cancel_url": "https://...",         // Cancel redirect
  "auto_redirect": true,               // Auto-redirect on success
  "metadata": {                        // Custom metadata
    "ips_transaction_id": "123",
    "ips_invoice_id": "456"
  }
}
```

Response:
```json
{
  "id": "checkout-session-flake-id",
  "token": "checkout-session-token",   // Used by paynow.js
  "url": "https://checkout.paynow.gg/..." // Redirect URL
}
```

**Implementation choice**: Redirect to `url` (simpler, like Stripe/Polar) vs paynow.js overlay. **Recommend redirect** for consistency with sibling gateways.

### 2.4 Order Model (Key Fields)

| Field | Type | Description |
|-------|------|-------------|
| `id` | FlakeId | Unique order ID |
| `pretty_id` | string | Human-readable (`pn-XXXXX`) |
| `status` | enum | `created`, `completed`, `canceled`, `refunded`, `chargeback` |
| `type` | enum | `one_time`, `subscription`, `mixed` |
| `subtotal_amount` | int64 | Minor units (cents) |
| `discount_amount` | int64 | Discount applied |
| `tax_amount` | int64 | Tax collected |
| `total_amount` | int64 | Total charged |
| `currency` | string | Currency code |
| `billing_name` | string | Customer billing name |
| `billing_email` | string | Customer email |
| `billing_country` | string | Country code |
| `customer_ip` | string | Customer IP at checkout |
| `lines` | array | OrderLineDto items |
| `checkout_id` | FlakeId | Source checkout session |
| `created_at` | datetime | Order creation time |
| `completed_at` | datetime | Order completion time |

### 2.5 Payment Model (Key Fields)

| Field | Type | Description |
|-------|------|-------------|
| `id` | FlakeId | Payment ID |
| `order_id` | FlakeId | Associated order |
| `amount` | int64 | Amount in minor units |
| `currency` | string | Currency code |
| `status` | enum | `created`, `pending`, `completed`, `canceled`, `failed`, `refunded`, `chargeback` |
| `gateway` | enum | Stripe, PayPal, etc. |
| `method` | object | PaymentMethodDto with card/wallet details |
| `gateway_fee_amount` | int64 | Gateway processing fee |
| `tax_amount` | int64 | Tax amount |
| `store_net_amount` | int64 | Net amount to store |
| `refunds` | array | RefundDto objects |

### 2.6 Refund Model

| Field | Type | Description |
|-------|------|-------------|
| `id` | FlakeId | Refund ID |
| `payment_id` | FlakeId | Source payment |
| `amount` | int64 | Refund amount (minor units) |
| `status` | enum | `created`, `approved`, `processing`, `completed`, `canceled`, `failed` |
| `failure_reason` | string | Error details if failed |

**Important**: PayNow's refund API (`POST /orders/{orderId}/refund`) refunds **per order** or **per order line** — not by arbitrary dollar amount. `SUPPORTS_PARTIAL_REFUNDS = FALSE` in the gateway class.

### 2.7 Webhook System

**Signature verification**:
1. Extract `PayNow-Signature` and `PayNow-Timestamp` headers
2. `PayNow-Timestamp` is Unix milliseconds
3. Build signed payload: `{timestamp}.{raw_body}`
4. HMAC-SHA256 with webhook secret
5. Constant-time compare against `PayNow-Signature`
6. Reject if timestamp older than 5 minutes (300 seconds)

**Event types we subscribe to** (REQUIRED_WEBHOOK_EVENTS):
- `OnOrderCompleted` — approve IPS transaction
- `OnRefund` — record refund on IPS transaction
- `OnChargeback` — mark dispute, optionally ban member
- `OnChargebackClosed` — log resolution
- `OnSubscriptionActivated` — (future) subscription support
- `OnSubscriptionRenewed` — (future) subscription support
- `OnSubscriptionCanceled` — (future) subscription support

**Retry policy**: PayNow retries with exponential backoff when response is non-2xx.

**PayNow webhook IP addresses** (for optional allowlisting):
- `34.203.112.123`
- `54.208.125.169`
- `54.86.24.96`

### 2.8 Customer Model

Customers in PayNow are identified by FlakeId and can be looked up by Steam ID, Minecraft UUID, Xbox XUID, or name. For our IPS integration:
- Create customer via `POST /stores/{storeId}/customers` with `name` = IPS member name
- Store PayNow customer FlakeId in `cm_profiles[gateway_id]` on the IPS member
- Lookup via `GET /stores/{storeId}/customers/lookup?id={paynow_customer_id}`

### 2.9 Coupon System

PayNow coupons support:
- **Types**: `percent`, `amount` (fixed)
- **Duration**: `once`, `forever`, `repeating` (with `duration_in_months`)
- **Limits**: Store-wide (`redeem_limit_store_amount`) and per-customer (`redeem_limit_customer_amount`)
- **Scoping**: By product IDs or tag IDs
- **Scheduling**: `usable_at` and `expires_at` datetime windows

For IPS coupon forwarding: calculate invoice discount, create a one-time PayNow coupon via `POST /stores/{storeId}/coupons`, attach to checkout session.

### 2.10 Supported Payment Methods

PayNow supports multiple payment gateways under the hood:
- Stripe (card, Link, Klarna, iDEAL, P24, Bancontact, Alipay)
- PayPal
- ForumPay (crypto)
- SteamSkins (inventory trading)
- Nuvei
- PagSeguro
- Tazapay

The actual available methods depend on the store's PayNow configuration.

### 2.11 Currency Support

- All amounts in **smallest currency unit** (cents for USD/EUR)
- Presentment currency support (customer sees different currency than settlement)
- `fx_rate` field for conversion tracking
- String-formatted amounts available (e.g., `total_amount_str` = `"$12.99"`)

## 3. App Structure

```
app-source/
├── data/
│   ├── application.json        # App metadata, version
│   ├── versions.json           # Version history
│   ├── modules.json            # admin/monitoring, front/webhook
│   ├── hooks.json              # 5 hooks (gateway, theme x2, member tab, invoice)
│   ├── extensions.json         # AdminNotifications, MemberACPProfileBlocks
│   ├── tasks.json              # pncWebhookReplay (15m), pncIntegrityMonitor (5m)
│   ├── schema.json             # pnc_webhook_forensics table
│   ├── acpmenu.json            # Monitoring tab entries
│   ├── acprestrictions.json    # integrity_view, forensics_view
│   ├── settings.json           # Empty (settings stored on gateway record)
│   ├── acpsearch.json          # Empty
│   ├── widgets.json            # Empty
│   └── themesettings.json      # Empty
├── sources/
│   └── XPaynowCheckout/
│       └── XPaynowCheckout.php # Gateway class extending \IPS\nexus\Gateway
├── hooks/
│   ├── code_GatewayModel.php   # Register gateway in Gateway::gatewayClasses()
│   ├── code_memberProfileTab.php  # ACP member profile integration
│   ├── invoiceViewHook.php     # Inject settlement data into invoice view
│   ├── theme_pnc_clients_settle.php  # Settlement card (front-end)
│   └── theme_pnc_print_settle.php    # Settlement card (print)
├── modules/
│   ├── admin/
│   │   └── monitoring/
│   │       ├── integrity.php   # Integrity panel ACP controller
│   │       └── forensics.php   # Forensics viewer ACP controller
│   └── front/
│       └── webhook/
│           └── webhook.php     # Webhook endpoint controller
├── tasks/
│   ├── pncWebhookReplay.php    # Replay failed webhooks (every 15min)
│   └── pncIntegrityMonitor.php # Health check + forensics prune (every 5min)
├── extensions/
│   └── core/
│       ├── AdminNotifications/
│       │   └── PaymentIntegrity.php  # ACP alert notifications
│       └── MemberACPProfileBlocks/
│           └── PaynowPaymentSummary.php  # Member profile payment block
└── dev/
    ├── lang.php                # Language strings
    └── html/
        ├── admin/monitoring/
        │   ├── integrity.phtml       # Integrity panel template
        │   └── paymentSummary.phtml  # Member payment summary block
        ├── front/clients/
        │   └── settlement.phtml      # Invoice settlement card
        └── global/invoices/
            └── printSettle.phtml     # Print invoice settlement
```

## 4. Payment Flow (Detailed)

### 4.1 Checkout Flow (auth → redirect → webhook → markPaid)

```
Customer clicks Pay
        │
        ▼
Gateway::auth($transaction)
        │
        ├─ 1. Resolve/create PayNow customer (cm_profiles lookup)
        │     POST /v1/stores/{storeId}/customers  (if new)
        │
        ├─ 2. Build checkout lines from invoice items
        │     Each IPS invoice item → PayNow line item
        │     Map IPS package → PayNow product_id (or use inline_product)
        │
        ├─ 3. Calculate IPS coupons/discounts
        │     If discount exists → create one-time PayNow coupon
        │     POST /v1/stores/{storeId}/coupons
        │
        ├─ 4. Create checkout session
        │     POST /v1/stores/{storeId}/checkouts
        │     metadata: { ips_transaction_id, ips_invoice_id, ips_member_id }
        │     return_url: transaction success URL
        │     cancel_url: invoice checkout URL with error
        │
        ├─ 5. Redirect to PayNow checkout URL
        │     Response contains { url: "https://checkout.paynow.gg/..." }
        │     JavaScript redirect (same pattern as Stripe)
        │
        ▼
Customer completes payment on PayNow
        │
        ▼
PayNow sends OnOrderCompleted webhook
        │
        ▼
webhook.php::manage()
        │
        ├─ Verify HMAC signature (PayNow-Signature + PayNow-Timestamp)
        ├─ Parse payload, extract order data
        ├─ Find IPS transaction via metadata.ips_transaction_id
        ├─ Fetch full order details: GET /v1/stores/{storeId}/orders/{orderId}
        ├─ Build settlement snapshot (subtotal, tax, total, method, refs)
        ├─ Store snapshot in transaction t_extra
        ├─ Set transaction gw_id = PayNow order ID
        ├─ Approve transaction → IPS calls invoice->markPaid()
        │
        ▼
Invoice marked paid → Nexus creates purchases
```

### 4.2 Refund Flow

```
Admin initiates refund from ACP
        │
        ▼
Gateway::refund($transaction)
        │
        ├─ POST /v1/stores/{storeId}/orders/{orderId}/refund
        │   Body: { order_line_id: optional }
        │
        ▼
PayNow processes refund
        │
        ▼
PayNow sends OnRefund webhook
        │
        ▼
webhook.php handles refund confirmation
```

### 4.3 Chargeback Flow

```
PayNow detects chargeback
        │
        ▼
OnChargeback webhook
        │
        ├─ Find IPS transaction
        ├─ If chargeback_ban enabled → ban member
        ├─ Update transaction status
        │
        ▼
(Later) OnChargebackClosed webhook
        │
        └─ Log resolution
```

## 5. Settlement Snapshots

When `OnOrderCompleted` fires, build and store a normalized snapshot in `t_extra`:

```php
$snapshot = array(
    'paynow_order_id'       => $order['id'],
    'paynow_pretty_id'      => $order['pretty_id'],      // "pn-XXXXX"
    'paynow_payment_id'     => $payment['id'],
    'payment_method'        => $payment['method']['type'],
    'subtotal_minor'        => (int) $order['subtotal_amount'],
    'subtotal_display'      => (string) $order['subtotal_amount_str'],
    'tax_minor'             => (int) $order['tax_amount'],
    'tax_display'           => (string) $order['tax_amount_str'],
    'discount_minor'        => (int) $order['discount_amount'],
    'discount_display'      => (string) $order['discount_amount_str'],
    'total_minor'           => (int) $order['total_amount'],
    'total_display'         => (string) $order['total_amount_str'],
    'currency'              => (string) $order['currency'],
    'billing_country'       => $order['billing_country'],
    'ips_invoice_total'     => $ipsInvoiceTotal,
    'has_total_mismatch'    => ($order['total_amount'] !== $ipsInvoiceTotal),
    'completed_at'          => $order['completed_at'],
);
```

## 6. Database Tables

### `pnc_webhook_forensics`
Records failed webhook deliveries for security audit.

| Column | Type | Description |
|--------|------|-------------|
| forensic_id | BIGINT PK AUTO | Row ID |
| event_type | VARCHAR(64) | Webhook event type |
| event_id | VARCHAR(64) NULL | PayNow event ID |
| failure_reason | VARCHAR(64) | `invalid_payload`, `missing_signature`, `invalid_signature`, `timestamp_too_old` |
| ip_address | VARCHAR(46) | Source IP |
| http_status | SMALLINT | Response code sent |
| payload_snippet | TEXT NULL | First 2000 chars of payload |
| created_at | INT | Unix timestamp |

Indexes: `PRIMARY`, `idx_created_at`, `idx_failure_created`

**Retention**: 90 days, pruned by `pncIntegrityMonitor` task.

## 7. Gateway Settings

All settings stored on `nexus_paymethods.m_settings` (JSON):

| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `api_key` | string | Yes | PayNow API key |
| `store_id` | string | Yes | PayNow store FlakeId |
| `webhook_url` | string | Auto | Webhook endpoint URL |
| `webhook_secret` | string | Auto | HMAC signing secret |
| `webhook_id` | string | Auto | PayNow webhook subscription ID |
| `return_url` | string | No | Custom success redirect |
| `cancel_url` | string | No | Custom cancel redirect |
| `chargeback_ban` | bool | No | Ban member on chargeback (default: true) |
| `replay_lookback` | int | No | Replay lookback seconds (default: 3600) |
| `replay_overlap` | int | No | Replay overlap seconds (default: 300) |
| `replay_max_events` | int | No | Max events per replay (default: 100) |

## 8. Hooks

| Hook File | Type | Target Class | Purpose |
|-----------|------|-------------|---------|
| `code_GatewayModel` | C | `\IPS\nexus\Gateway` | Register `XPaynowCheckout` in `gatewayClasses()` |
| `theme_pnc_clients_settle` | S | `\IPS\Theme\class_nexus_front_clients` | Settlement card in invoice view |
| `theme_pnc_print_settle` | S | `\IPS\Theme\class_nexus_global_invoices` | Settlement card in print invoice |
| `code_memberProfileTab` | C | `\IPS\core\extensions\core\MemberACPProfileTabs\Main` | ACP member profile block |
| `invoiceViewHook` | C | `\IPS\nexus\modules\front\clients\invoices` | Inject settlement data into invoice controller |

## 9. Tasks

| Task Key | Interval | Purpose |
|----------|----------|---------|
| `pncWebhookReplay` | 15 min | Fetch failed webhook deliveries from PayNow API, resend |
| `pncIntegrityMonitor` | 5 min | Check alert conditions, prune forensics (90-day retention) |

## 10. Extensions

| Extension | Type | Purpose |
|-----------|------|---------|
| `PaymentIntegrity` | AdminNotifications | Raise ACP alerts for webhook errors, stale replay, mismatches |
| `PaynowPaymentSummary` | MemberACPProfileBlocks | Show chargeback/refund summary on member profile |

## 11. Implementation Priorities

### Phase 1 — Core Checkout (MVP)
1. `auth()` — Create checkout session, redirect to PayNow
2. `webhook.php` — Handle `OnOrderCompleted` → approve transaction
3. `testSettings()` — Validate API key, auto-create webhook
4. `code_GatewayModel` hook — Register gateway
5. Gateway settings form — API key, store ID, webhook display
6. Settlement snapshot — Build and store in t_extra

### Phase 2 — Refund & Chargeback
7. `refund()` — Call PayNow refund API
8. `OnRefund` webhook handler
9. `OnChargeback` + `OnChargebackClosed` handlers
10. Chargeback ban logic
11. Member profile block (chargeback/refund counts)

### Phase 3 — Monitoring & Resilience
12. Integrity panel — Webhook health, replay status, mismatch table
13. Forensics viewer — Table\Db with filters
14. `pncWebhookReplay` task — Fetch history, resend failed
15. `pncIntegrityMonitor` task — Alert conditions, forensics prune
16. AdminNotification alerts
17. Acknowledge errors button

### Phase 4 — Polish
18. Invoice view settlement card (theme hooks)
19. Print invoice settlement card
20. IPS coupon forwarding (create PayNow coupon for IPS discounts)
21. `gatewayUrl()` — Link to PayNow dashboard
22. `checkValidity()` — Currency validation

### Phase 5 — Subscription Support (Future)
23. `OnSubscriptionActivated` / `OnSubscriptionRenewed` / `OnSubscriptionCanceled` handlers
24. Recurring billing support in `auth()` (line-level `subscription: true`)

## 12. Key Differences from Stripe/Polar

| Aspect | Stripe | Polar | PayNow |
|--------|--------|-------|--------|
| Auth header | `Bearer {secret}` | `Bearer {token}` | `apikey {key}` |
| Checkout create | `POST /checkout/sessions` | `POST /v1/checkouts` | `POST /v1/stores/{id}/checkouts` |
| Customer ID | Stripe customer ID | Polar customer ID | PayNow FlakeId |
| Webhook sig | Stripe-Signature (t=...v1=...) | Standard Webhooks (base64 HMAC) | `PayNow-Signature` (hex HMAC) + `PayNow-Timestamp` (ms) |
| Amounts | Minor units (cents) | Minor units (cents) | Minor units (cents) |
| Partial refund | Yes | Yes | No (order-level only) |
| Subscriptions | Stripe Billing | Polar subscriptions | PayNow subscriptions |
| Tax | Stripe Tax (auto) | N/A | Included in amounts |
| Invoice gen | Stripe auto-creates | Manual/webhook | Via order data |

## 13. Error Handling

PayNow error response format:
```json
{
  "status": 400,
  "code": "InvalidInput",
  "message": "Human-readable error description",
  "trace_id": "debug-id",
  "errors": [
    {
      "code": "validation_error",
      "message": "field is required",
      "path": ["lines", "0", "product_id"],
      "validation": "required"
    }
  ]
}
```

## 14. IPS4 Coding Standards Reminders

- All SQL parameterized: `array('col=?', $val)`
- `mb_*` string functions only (no `strlen`, `substr`, etc.)
- No `private` — use `protected` or `public`
- Code hooks: start with `//<?php`, class extends `_HOOK_CLASS_`, wrap in try/catch
- `hookData()` must return `: array`
- Task keys globally unique — all prefixed with `pnc`
- CSRF: `$csrfProtected = TRUE` on ACP controllers, `csrfCheck()` on actions
- Templates: `{$var}` auto-escaped, `{$var|raw}` for trusted HTML only
- Settings stored on `nexus_paymethods` gateway record, not `core_sys_conf_settings`
