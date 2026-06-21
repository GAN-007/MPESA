# GAN-TECH Merchant Payment Operations SaaS Blueprint

## Product Name

GAN-TECH Merchant Payment Operations SaaS, code-named GANPay.

## Product Positioning

GANPay is a merchant payment operations platform built around M-PESA first. It is not only an SDK. It is the operational layer businesses use to collect payments, reconcile them, trigger webhooks, manage payment links, monitor transactions, and prepare settlements.

The existing MPESA SDK remains the Daraja connector layer. The SaaS platform sits above it and owns merchant onboarding, authentication, API keys, hosted checkout, payment state, reconciliation, ledger entries, settlements, billing, audit logs, and operational dashboards.

## Core Product Principle

Start as a merchant payment operations SaaS before becoming a fully regulated multi-rail payment gateway. M-PESA STK Push, C2B confirmation, payment links, hosted checkout, webhooks, reconciliation, ledger, and settlements come first. Cards, PayPal, bank transfers, Airtel Money, subscriptions, invoices, and POS QR payments come later through licensed processors or regulated partners.

## Target Users

1. Merchants that need to collect M-PESA payments online.
2. Developers integrating payments into websites, mobile apps, ERPs, CRMs, marketplaces, schools, SACCOs, clinics, shops, and subscription systems.
3. Finance teams that need reconciliation, settlement records, exports, transaction timelines, and audit trails.
4. Operations teams that need failed payment recovery, manual verification, dispute support, and callback visibility.

## SaaS Modules

### Merchant Management

- Merchant registration.
- Business profile.
- Business verification status.
- Owner profile.
- KYB and KYC document references.
- Team members.
- Roles and permissions.
- Test mode and live mode.
- Merchant status lifecycle: pending, active, suspended, rejected, closed.

### Credentials and API Keys

- Encrypted M-PESA credentials per merchant.
- Separate sandbox and live credentials.
- API key creation, rotation, and revocation.
- IP allowlists.
- Secret hashing.
- Last-used tracking.
- Scoped keys for read, write, checkout, refund, settlement, and admin operations.

### Payment Intents

PaymentIntent is the internal payment object. It hides raw Daraja complexity and exposes consistent platform states.

States:

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

### Payment Links

Payment links allow merchants to create hosted payment pages without writing code.

Example public route:

`/pay/pl_abc123`

Supported fields:

- amount
- currency
- description
- customer name
- customer phone
- merchant order reference
- expiry time
- success URL
- cancel URL
- metadata

### Hosted Checkout

Hosted checkout allows customers to enter a phone number, receive STK Push, and view live payment status.

Flow:

1. Merchant creates a payment intent or payment link.
2. Customer opens checkout.
3. Customer enters M-PESA phone number.
4. Platform initiates STK Push.
5. Checkout polls payment status.
6. Safaricom callback updates payment.
7. Customer sees success or failure.
8. Merchant webhook is dispatched.

### Webhook Engine

The webhook engine dispatches signed merchant notifications.

Responsibilities:

- Store webhook endpoints.
- Sign payloads.
- Dispatch events.
- Retry failed deliveries with backoff.
- Store delivery attempts.
- Expose delivery logs.
- Support webhook testing.

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

### Reconciliation Engine

The reconciliation engine matches initiated payments, callbacks, queries, C2B confirmations, merchant order references, and settlement records.

Responsibilities:

- Query pending STK payments.
- Detect duplicate callbacks.
- Detect stale pending payments.
- Resolve failed or delayed callbacks.
- Create transaction timeline entries.
- Mark payments as expired where appropriate.
- Produce reconciliation reports.

### Ledger System

The platform must own an internal accounting ledger. Raw M-PESA responses are not enough.

Ledger requirements:

- Double-entry style ledger.
- Merchant receivable account.
- Platform fee account.
- Settlement payable account.
- Refund clearing account.
- Reversal clearing account.
- Immutable ledger entries.
- Payment-to-ledger traceability.

### Settlements

Settlement records summarize payable balances to merchants.

Responsibilities:

- Generate settlement batches.
- Attach settlement items.
- Track settlement status.
- Export settlement reports.
- Support manual settlement approval.
- Support future automated payouts.

### Billing

The SaaS can monetize through:

- monthly subscription plans
- transaction fees
- settlement fees
- premium analytics
- team seats
- webhook volume tiers
- reconciliation/export features

### Admin and Compliance

Admin tools must include:

- merchant approval
- suspicious activity review
- transaction lookup
- manual reconciliation
- audit logs
- credential status
- API usage logs
- account suspension
- dispute notes

Compliance notes:

- Treat this as a payments product.
- Get legal advice before production PSP positioning.
- Store personal data carefully.
- Do not touch raw cardholder data when card support is added.
- Use licensed processors for card tokenization and hosted card checkout.

## Recommended Repository Shape

The repo should become a monorepo:

```text
sdk/       Existing M-PESA SDK package
saas/      Laravel merchant operations SaaS
checkout/  Future hosted checkout frontend if separated
ops/       Deployment, Docker, infra, and runbooks
docs/      Product, API, schema, compliance, and Codex instructions
```

## First Production Milestone

Milestone 1 must deliver:

- SDK stabilized.
- Merchant registration.
- Merchant profile.
- Encrypted M-PESA credentials.
- API keys.
- Payment intents.
- STK Push initiation.
- Safaricom callback endpoint.
- Payment links.
- Hosted checkout.
- Webhook dispatch.
- Payment status polling.
- Transaction dashboard.
- Basic ledger entries.
- Settlement records.
- Audit logs.
- Docker Compose local environment.
- Tests and CI.
