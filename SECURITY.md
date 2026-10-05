# Security policy and production guidance

## Supported branch

Security fixes are targeted at the current `main` branch after review/merge.

## Secrets

Never commit:

- Daraja consumer secret,
- Lipa na M-PESA passkey,
- initiator plaintext password,
- generated security credential if your threat model treats it as reusable authentication material,
- private keys,
- payment-link HMAC secret,
- card PAN/CVV/PIN/OTP,
- customer authentication credentials.

Use your deployment platform's secret manager or environment injection.

## TLS

The bundled HTTP transports require peer and hostname verification. Do not disable TLS verification to work around a certificate problem; fix the trust chain or deployment network.

## Daraja security credential

Use the environment-correct Safaricom public certificate and RSA PKCS#1 v1.5 encryption for the initiator password. `DarajaProvider::encryptSecurityCredential()` performs this operation locally.

Never log the plaintext initiator password.

## Callbacks

Safaricom publishes API Gateway callback IPs in the Daraja Getting Started documentation. `CallbackSecurity` contains that list and allows explicitly configured additions.

IP allow-listing is one layer, not a complete authenticity mechanism. Use HTTPS, harden the callback route, parse only JSON, validate expected references/amounts, make callback handling idempotent, and reconcile independently.

When behind a load balancer/reverse proxy, trust forwarding headers only from known proxies after the edge strips client-supplied spoofed values.

## Idempotency

Every user/business operation should have a durable idempotency key. The SDK provides a file-backed implementation suitable for a shared single-host filesystem. Multi-node production systems should implement `IdempotencyStore` against a transactional shared store with locking/atomic compare-and-set semantics.

Provider-side IDs such as B2C `OriginatorConversationID` should also be persisted to make ambiguous retries reconcilable.

## Money

The SDK stores money using integer minor units. Do not use floating-point values as the authoritative ledger representation.

## Ledger and settlement

Treat provider callbacks and internal ledger posting as separate but correlated state transitions. Persist provider reference, internal payment ID, amount, currency, status and callback payload hash. Reconciliation should detect missing or inconsistent records rather than silently correcting them.

## Card data

`VirtualCardControls` accepts a token reference only. It is not a card vault or issuer. Never store PAN, CVV, track data or PIN in this package. Use a PCI-compliant acquirer/processor/tokenization service and keep sensitive card data outside your application when possible.

## Reporting vulnerabilities

Do not open a public issue containing secrets, exploitable production endpoints, credentials or customer data. Use the repository owner's private security-reporting channel where available.
