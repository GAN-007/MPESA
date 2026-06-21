# GAN-TECH Payment SaaS Security and Compliance Guide

## Security Position

GANPay handles payment initiation, payment status, merchant credentials, customer phone numbers, receipts, reconciliation records, and settlement records. The platform must be treated as sensitive financial infrastructure from day one.

## Non-Negotiable Rules

1. Never commit live M-PESA credentials.
2. Keep SSL verification enabled.
3. Store merchant credentials encrypted at rest.
4. Store API secrets as hashes, not plaintext.
5. Use HTTPS everywhere.
6. Sign merchant event deliveries.
7. Keep immutable audit logs.
8. Keep test and live mode separated.
9. Do not support card data collection directly until proper PCI scope is designed.
10. Get Kenyan fintech regulatory advice before operating as a production PSP or settlement intermediary.

## Credential Storage

Merchant M-PESA credentials must be encrypted using application key management. Recommended fields:

- consumer_key_encrypted
- consumer_secret_encrypted
- passkey_encrypted
- initiator_name_encrypted
- security_credential_encrypted

Credential access must be logged in audit logs.

## API Key Security

API keys must have:

- public prefix
- hashed secret
- scopes
- environment
- optional IP allowlist
- created timestamp
- last used timestamp
- revoked timestamp

Only show the full API secret once during creation.

## Tenant Isolation

Every tenant-owned model must contain `merchant_id`. Queries must always be scoped by merchant. Admin bypass must be explicit and audited.

## Payment State Integrity

Payment status changes must be controlled by a state machine. Random status overwrites must not be allowed.

Allowed examples:

- created to pending_customer_authorization
- pending_customer_authorization to paid
- pending_customer_authorization to failed
- paid to reversed
- paid to refunded
- paid to partially_refunded

## Ledger Integrity

Ledger entries must be immutable. Corrections must be posted as new reversing entries, never by editing old ledger rows.

## Event Delivery Security

Merchant event deliveries must include:

- event id
- event type
- timestamp
- signature
- raw JSON body

Signature should use HMAC SHA-256 over timestamp and body.

## Callback Handling

Provider callback handling must:

- store raw payload
- tolerate duplicate callbacks
- be idempotent
- map to internal payment state
- preserve provider identifiers
- enqueue merchant event delivery
- never trust callback alone where reconciliation is required

## Data Protection

Minimize customer data. Store only what is needed for payment operations and reconciliation.

Recommended customer fields:

- name optional
- phone required for M-PESA
- email optional
- merchant reference
- metadata

## Compliance Notes

M-PESA-only operations reduce card compliance exposure, but this is still a financial product. Before going live as a payment facilitator, gateway, aggregator, or settlement intermediary, get formal advice on Kenyan payment service provider obligations, AML, KYC, data protection, consumer protection, and settlement handling.

When card support is added, do not store, process, or transmit raw card numbers in this platform. Use licensed processors and hosted/tokenized card collection.

## Production Checklist

- Application encryption key rotated and protected.
- Database backups configured.
- Queue workers supervised.
- Failed jobs monitored.
- Error tracking enabled.
- Request IDs added to logs.
- Audit logging enabled.
- Rate limiting enabled.
- API key scopes enforced.
- Merchant isolation tests passing.
- Payment state machine tests passing.
- Reconciliation jobs enabled.
- Event delivery retries enabled.
- Settlement approval controls enabled.
