# X PayNow Checkout Flow

## Current Scope

This document captures the target architecture for `xpaynowcheckout`.

## Runtime Entry Points

- Gateway class: `app-source/sources/XPaynowCheckout/XPaynowCheckout.php`
- Webhook endpoint: `index.php?app=xpaynowcheckout&module=webhook&controller=webhook`
- Integrity panel: `app-source/modules/admin/monitoring/integrity.php`
- Forensics viewer: `app-source/modules/admin/monitoring/forensics.php`
- Replay task: `app-source/tasks/pncWebhookReplay.php`
- Integrity monitor: `app-source/tasks/pncIntegrityMonitor.php`

## End-to-End Checkout Flow

```
Customer starts checkout (Nexus invoice/transaction)
        │
        ▼
Gateway::auth($transaction)
        │
        ├─ 1. Resolve/create PayNow customer
        │     Lookup via cm_profiles[gateway_id] on IPS member
        │     If not found: POST /v1/stores/{storeId}/customers
        │     Store PayNow customer FlakeId in cm_profiles
        │
        ├─ 2. Build checkout lines
        │     Each IPS invoice item → PayNow line item
        │     Map IPS package → PayNow product_id
        │     Set quantity, metadata, subscription flag
        │
        ├─ 3. Handle IPS coupons (if present)
        │     Calculate discount from negative-amount invoice items
        │     Create one-time PayNow coupon: POST /v1/stores/{storeId}/coupons
        │     Attach coupon_id to checkout session
        │
        ├─ 4. Create checkout session
        │     POST /v1/stores/{storeId}/checkouts
        │     Include: customer_id, lines[], coupon_id, return_url, cancel_url, metadata
        │     Metadata: { ips_transaction_id, ips_invoice_id, ips_member_id }
        │
        ├─ 5. Redirect to PayNow
        │     Response: { id, token, url }
        │     JavaScript redirect to checkout URL
        │
        ▼
Customer completes payment on PayNow hosted page
        │
        ▼
PayNow sends OnOrderCompleted webhook
        │
        ▼
webhook.php::manage()
        │
        ├─ Verify HMAC signature (PayNow-Signature + PayNow-Timestamp)
        ├─ Enforce timestamp freshness (300-second tolerance)
        ├─ Parse event payload
        ├─ Dispatch to event-specific handler
        │
        ▼
handleOrderCompleted($body, $gateway)
        │
        ├─ Resolve IPS transaction via 3-level fallback:
        │     1. body.metadata.ips_transaction_id
        │     2. body.checkout.metadata.ips_transaction_id
        │     3. DB lookup by order_id/checkout_id/id as gw_id
        ├─ Check idempotency (skip if already paid/refunded)
        ├─ Build settlement snapshot from webhook body
        │     (subtotal, tax, discount, total, billing info, mismatch detection)
        ├─ Store snapshot in t_extra (xpaynowcheckout_snapshot key)
        │     and invoice i_status_extra
        ├─ Update t_gw_id = PayNow order ID
        ├─ Approve transaction via checkFraudRulesAndCapture()
        │     → triggers invoice->markPaid()
        │
        ▼
Invoice marked paid → Nexus creates purchases
```

## Refund Flow

```
Admin initiates refund from ACP
        │
        ▼
Gateway::refund($transaction)
        │
        ├─ POST /v1/stores/{storeId}/orders/{orderId}/refund
        │   PayNow refunds order-level (no partial dollar amounts)
        │
        ▼
PayNow processes refund
        │
        ▼
OnRefund webhook
        │
        ├─ Resolve IPS transaction (3-level fallback)
        ├─ Check terminal status gating (completed/approved only)
        ├─ Store refund metadata in t_extra (refund_id, payment_id, amount, status)
        ├─ Set transaction status to STATUS_REFUNDED
        │
        ▼
Transaction refunded in IPS
```

## Chargeback Flow

```
PayNow detects chargeback from upstream gateway
        │
        ▼
OnChargeback webhook
        │
        ├─ Resolve IPS transaction (3-level fallback)
        ├─ Store chargeback metadata in t_extra
        ├─ Set transaction status to STATUS_DISPUTED
        ├─ If chargeback_ban enabled → ban member permanently
        ├─ Revoke benefits (invoice markUnpaid/canceled)
        ├─ Send admin notification
        │
        ▼
(Later) OnChargebackClosed webhook
        │
        ├─ Resolve IPS transaction
        ├─ Update chargeback metadata with resolution + close_event_id
        ├─ If won → restore STATUS_PAID, re-mark invoice paid if balance zero
        ├─ If lost → set STATUS_REFUNDED
        └─ (No automatic unban — admin reviews manually)
```

## Webhook Replay Flow

```
pncWebhookReplay task runs (every 15 min)
        │
        ├─ Fetch delivery history: GET /v1/stores/{storeId}/webhooks/{id}/history
        ├─ Filter for failed deliveries within lookback window
        ├─ Deduplicate by event ID
        ├─ For each candidate:
        │     Build HMAC headers with webhook secret
        │     Forward to local webhook controller
        │     Log result
        ├─ Save replay cursor state (last_run_at, last_event_id, replayed_count)
        │
        ▼
Replay state available in ACP integrity panel
```

## Integrity Monitor Flow

```
pncIntegrityMonitor task runs (every 5 min)
        │
        ├─ Collect alert stats via gateway::collectAlertStats()
        │     Count webhook errors in last 1h/24h
        │     Check replay state freshness
        │     Count total mismatches
        ├─ Raise/clear admin notifications based on thresholds
        ├─ Prune forensics rows older than 90 days
        │
        ▼
ACP notifications surface alerts to admins
```

## Webhook Invariants

- Reject missing/invalid signatures.
- Enforce 300-second timestamp freshness tolerance.
- Process each event idempotently (duplicate event_id returns 200).
- Never regress already-terminal transaction states.
- Log all validation failures into `pnc_webhook_forensics` for ACP review.

## Data Model Notes

- `pnc_webhook_forensics` stores webhook validation and processing failures.
- `t_extra` stores normalized provider snapshot fields (key: `xpaynowcheckout_snapshot`).
- `i_status_extra` stores invoice-level settlement display metadata.
- `cm_profiles[gateway_id]` on IPS member stores PayNow customer FlakeId.
- Gateway settings stored on `nexus_paymethods.m_settings` (not `core_sys_conf_settings`).

## PayNow API Endpoints Used

| Action | Method | Endpoint |
| --- | --- | --- |
| Create customer | POST | `/v1/stores/{storeId}/customers` |
| Lookup customer | GET | `/v1/stores/{storeId}/customers/lookup` |
| Create checkout | POST | `/v1/stores/{storeId}/checkouts` |
| Get order | GET | `/v1/stores/{storeId}/orders/{orderId}` |
| Refund order | POST | `/v1/stores/{storeId}/orders/{orderId}/refund` |
| Create coupon | POST | `/v1/stores/{storeId}/coupons` |
| Create webhook | POST | `/v1/stores/{storeId}/webhooks` |
| List webhooks | GET | `/v1/stores/{storeId}/webhooks` |
| Resend webhook | POST | `/v1/stores/{storeId}/webhooks/resend` |
| Webhook history | GET | `/v1/stores/{storeId}/webhooks/{id}/history` |
