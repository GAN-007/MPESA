# GAN-TECH Merchant Payment Operations SaaS API Specification

Base path: `/api/v1`

Authentication: Bearer API key.

All API requests are scoped to one merchant through the authenticated API key. Public IDs are used in responses. Internal numeric IDs are never exposed.

## Payment States

- created
- pending_customer_authorization
- processing
- paid
- failed
- cancelled
- expired
- reversed
- refunded
- partially_refunded

## Errors

```json
{
  "error": {
    "type": "validation_error",
    "code": "invalid_amount",
    "message": "Amount must be greater than zero.",
    "request_id": "req_123"
  }
}
```

## Idempotency

Write endpoints support `Idempotency-Key`. A reused key with the same payload returns the original response. A reused key with a different payload returns a conflict.

## Payments

### Create STK Push Payment

`POST /api/v1/payments/stk-push`

Request:

```json
{
  "amount": 1000,
  "currency": "KES",
  "phone_number": "254712345678",
  "merchant_reference": "ORDER-1001",
  "description": "Order payment",
  "metadata": {
    "order_id": "1001"
  }
}
```

Response:

```json
{
  "id": "pi_abc123",
  "status": "pending_customer_authorization",
  "amount": 1000,
  "currency": "KES",
  "phone_number": "254712345678",
  "merchant_reference": "ORDER-1001",
  "created_at": "2026-06-21T14:00:00Z"
}
```

### Get Payment

`GET /api/v1/payments/{payment_id}`

### List Payments

`GET /api/v1/payments?status=paid&from=2026-06-01&to=2026-06-21`

### Cancel Payment

`POST /api/v1/payments/{payment_id}/cancel`

## Payment Links

### Create Payment Link

`POST /api/v1/payment-links`

Request:

```json
{
  "title": "June Subscription",
  "description": "Monthly payment",
  "amount": 2500,
  "currency": "KES",
  "merchant_reference": "SUB-2026-06",
  "success_url": "https://merchant.example/success",
  "cancel_url": "https://merchant.example/cancel"
}
```

Response:

```json
{
  "id": "pl_abc123",
  "url": "https://pay.gan-tech.example/pay/pl_abc123",
  "status": "active"
}
```

## Checkout

Public checkout routes:

- `GET /pay/{payment_link_slug}`
- `POST /checkout/{checkout_session_id}/stk-push`
- `GET /checkout/{checkout_session_id}/status`

## Refunds and Reversals

- `POST /api/v1/refunds`
- `POST /api/v1/reversals`

## Merchant Event Endpoints

Merchants can register event delivery URLs for payment and settlement notifications.

Dashboard/API endpoints:

- `POST /api/v1/event-endpoints`
- `POST /api/v1/event-endpoints/{id}/test`
- `GET /api/v1/event-deliveries`

Events:

- payment.created
- payment.pending
- payment.paid
- payment.failed
- payment.expired
- payment.reversed
- payment.refunded
- settlement.created
- settlement.paid

## Transactions

`GET /api/v1/transactions`

Filters: status, type, payment_id, from, to, receipt, provider_reference.

## Settlements

- `GET /api/v1/settlements`
- `GET /api/v1/settlements/{settlement_id}`
- `GET /api/v1/settlements/{settlement_id}/items`

## Merchant Settings

- `GET /api/v1/merchant`
- `PATCH /api/v1/merchant`

## API Keys

Dashboard-only operations:

- create API key
- rotate API key
- revoke API key
- list API key usage

## M-PESA Provider Callback Routes

The platform needs internal provider routes for STK, C2B, B2C, B2B, reversal, balance, and transaction-status result handling. These routes store raw payloads, update payment state, write timeline records, trigger reconciliation, and dispatch merchant event deliveries.
