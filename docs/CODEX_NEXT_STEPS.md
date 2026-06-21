# Codex Next Steps

Branch: `gan-tech-merchant-saas-20260621`

Goal: complete GAN-TECH Merchant Payment Operations SaaS first.

## Rules

- Keep the MPESA SDK as the Daraja connector.
- Build the SaaS layer around merchants, payments, checkout, events, reconciliation, ledger, settlements, billing, audit, and compliance.
- Keep SSL verification enabled by default.
- Keep tenant isolation through merchant_id.
- Do not store live credentials in Git.
- Add tests before merge.

## Work Plan

1. Finish the SDK source files under `src/`.
2. Move SDK into `sdk/` after backward compatibility is protected.
3. Install Laravel inside `saas/`.
4. Add PostgreSQL and Redis configuration.
5. Add merchant registration and onboarding.
6. Add encrypted M-PESA credentials.
7. Add hashed merchant API keys.
8. Add payment intents and payment transactions.
9. Add payment links and checkout sessions.
10. Add STK Push initiation through the SDK.
11. Add provider result handling for STK, C2B, B2C, B2B, reversals, balance, and transaction status.
12. Add merchant event endpoint delivery with retries.
13. Add reconciliation jobs for pending and delayed payments.
14. Add double-entry ledger entries.
15. Add settlements and settlement items.
16. Add refunds and reversals.
17. Add dashboard pages for merchants.
18. Add admin pages for compliance and operations.
19. Add audit logs.
20. Add tests and CI.

## Required Laravel Models

Merchant, MerchantUser, MerchantCredential, ApiKey, Customer, PaymentIntent, PaymentTransaction, PaymentLink, CheckoutSession, EventEndpoint, EventDelivery, LedgerAccount, LedgerEntry, Refund, Reversal, Settlement, SettlementItem, AuditLog, IdempotencyKey.

## Required Jobs

QueryPendingStkPayment, ReconcileMpesaTransaction, ExpireStalePaymentIntent, GenerateDailySettlement, DispatchMerchantEvent, RetryFailedMerchantEvent.

## Required Tests

SDK tests, merchant isolation tests, payment state tests, API key tests, checkout tests, provider result tests, event delivery tests, ledger tests, settlement tests, reconciliation tests.

## Validation

Run composer validation, install dependencies, run lint, run tests, run migrations, and open a pull request only when all checks pass.
