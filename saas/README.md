# GAN-TECH Merchant Payment Operations SaaS

This folder is the Laravel-first SaaS application layer for GANPay.

The root SDK remains the M-PESA Daraja connector. This SaaS layer turns that connector into merchant-facing payment operations infrastructure.

## First Milestone

The first working SaaS milestone must include:

- merchant registration and login
- merchant onboarding
- encrypted M-PESA credentials
- API keys
- payment intents
- STK Push initiation
- payment links
- hosted checkout
- provider result handling
- merchant event delivery
- reconciliation jobs
- ledger entries
- settlement records
- transaction exports
- admin audit logs
- test/live mode separation

## Suggested Stack

- Laravel 11 or 12
- PostgreSQL
- Redis
- Laravel queues
- Horizon
- Sanctum for dashboard auth
- hashed API keys for merchant API access
- PHPUnit or Pest
- Docker Compose
- OpenAPI documentation

## Domain Structure

```text
saas/app/Domain/Merchants
saas/app/Domain/ApiKeys
saas/app/Domain/Payments
saas/app/Domain/Checkout
saas/app/Domain/EventDelivery
saas/app/Domain/Reconciliation
saas/app/Domain/Ledger
saas/app/Domain/Settlements
saas/app/Domain/Billing
saas/app/Domain/Audit
saas/app/Domain/Compliance
saas/app/Jobs
saas/routes
saas/database/migrations
saas/openapi
```

## Development Rule

Do not expose raw Daraja responses directly to merchants. Normalize all provider behavior into GANPay payment states, event types, ledger entries, and settlement records.
