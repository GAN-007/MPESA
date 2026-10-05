# African Payment Product Analysis → Repository Architecture

## Scope

The source image compares high-visibility finance/payment apps in Nigeria, Kenya, South Africa and Egypt. The important engineering lesson is not to copy logos or bolt unrelated SDKs together. The durable value is to isolate the **payment capabilities and user journeys** that repeatedly win in different markets, then implement them as coherent domain primitives around a real M-PESA adapter.

This repository therefore distinguishes three layers:

1. **Network adapter** — a real authorized integration such as `DarajaProvider`.
2. **Payment operating layer** — routing, idempotency, risk, aliases, links, bills, ledger, settlement, agent liquidity, rewards and reconciliation.
3. **Channel/application layer** — web/mobile checkout, POS, USSD, QR scanner, merchant dashboard, agent app, investment UI, etc. These call the operating layer but are outside this PHP SDK's UI scope.

## Nigeria

### MoMo PSB / MTN MoMo pattern

Core problem solved: reach customers who may not depend on a conventional bank branch or card.

Typical journeys:

- Phone-number wallet onboarding.
- Cash-in and cash-out through an agent network.
- Wallet-to-wallet and wallet-to-bank transfer.
- Airtime/data and bill payment.
- Merchant payment.
- Transfer receipts/statements.
- Cross-border remittance in supported corridors.

Architecture learned from it:

- Phone or human-readable destination aliases cannot be coupled to one rail.
- Physical cash and electronic float are two different liquidity positions.
- A payment system needs receipts/history and reconciliation, not only a `POST /pay` operation.

Repository implementation:

- `AliasDirectory` / `PaymentAlias`.
- `AgentNetwork`, `Agent`, `AgentTransaction`.
- `BillerCatalog` / `BillPaymentService`.
- `Ledger` and `ReconciliationService`.
- `PaymentProvider` for the regulated network adapter.

### OPay pattern

Core problem solved: combine consumer financial services and merchant acceptance instead of forcing a small business to maintain separate bank, POS, bill-pay and reconciliation tools.

Typical journeys:

- Wallet/bank transfer.
- POS merchant acceptance.
- Transfer-number / account-based merchant payment.
- Instant transaction confirmation.
- Bills and value-added services.
- Merchant branch/employee views, history, settlement and reconciliation.

Architecture learned from it:

- Merchant acceptance must be channel-neutral: QR, account transfer, mobile-money prompt and online checkout should produce the same internal payment object.
- Merchant operations need idempotency and reconciliation because duplicate or ambiguous payments are an operational cost.

Repository implementation:

- `PaymentMethod` normalizes mobile money, bank transfer, card, QR, payment link, bill, wallet and cash intents.
- `PaymentRequest` / `PaymentResult` normalize the lifecycle.
- `PaymentRouter` and `ProviderRegistry` make the channel independent of a specific provider.
- `FileIdempotencyStore` and `ReconciliationService` address duplicate/ambiguous payment operations.

### Moniepoint pattern

Core problem solved: payments become more valuable when tied to the operating system of the merchant — sales, branches, inventory, staff, accounting and reporting.

The repository deliberately does **not** become an ERP. Instead it exposes the financial primitives an ERP can depend on:

- double-entry postings (`Ledger`),
- normalized provider references,
- settlement positions (`SettlementWallet`),
- reconciliation reports,
- deterministic idempotency.

That keeps business-management features outside the payment kernel while giving them reliable financial state.

### PalmPay pattern

Core problem solved: make frequent low-friction transfers and bills habit-forming through broad merchant acceptance, status visibility and rewards.

Typical journeys:

- Bank/wallet transfers.
- “Pay with transfer” to a merchant account/virtual account.
- Bills (airtime, data, electricity, TV, transport, education).
- Real-time status.
- Cashback/reward offers.
- Merchant pay-in, payout and bulk operations.

Repository implementation:

- `Biller` validation and `BillPaymentService`.
- `RewardsEngine`.
- `PaymentStatus` lifecycle.
- `PaymentLinkService` for no-hardware acceptance.
- Provider-neutral orchestration for future authorized pay-in/payout adapters.

## Kenya

### M-PESA pattern

Core problem solved: phone-first stored value plus ubiquitous person/merchant/business flows.

Journeys implemented directly through Daraja:

- STK Push / M-PESA Express.
- C2B notifications and sandbox simulation.
- B2C disbursement.
- B2B / Business Pay Bill / Business Buy Goods.
- account balance and transaction-status operations.
- reversal for supported transaction classes.
- tax remittance.
- Dynamic QR.
- B2B Express Checkout.
- Bill Manager.
- Pull Transactions.

Important operational lesson: an API acknowledgement is not settlement. Daraja flows are often asynchronous, so the SDK represents initial STK acceptance as `PENDING`, normalizes callbacks and provides status/reconciliation utilities.

### Airtel Money pattern

Core problem solved: a second mobile-money network gives consumers and businesses interoperability, transfers, pay-bill/buy-goods, bulk disbursement and international transfer options.

Repository implication: checkout must not hard-code “mobile money = M-PESA.” `PaymentProvider` and `ProviderRegistry` make M-PESA one provider. An authorized Airtel Money adapter can implement the same interface and participate in routing without changing business code.

### Equity Mobile / NCBA NOW pattern

Core problem solved: bank customers expect one surface that spans own-bank accounts, other banks, mobile wallets, bills, cards, statements and lending.

Architecture learned from it:

- Payment destinations are heterogeneous.
- A successful transaction should produce a consistent receipt/reference regardless of rail.
- Duplicate-payment protection and transaction status matter when switching across bank/mobile networks.

Repository implementation:

- multi-rail `PaymentMethod`.
- aliases and provider routing.
- idempotency.
- reconciliation.
- card policy primitives.

### Pesapal pattern

Core problem solved: a merchant should integrate once and accept multiple local/international methods through API, plugins, payment pages/links, POS and QR.

Repository implementation:

- `PaymentProvider` contract and router.
- signed `PaymentLinkService`.
- QR as a first-class `PaymentMethod` plus Daraja Dynamic QR.
- normalized checkout result/status.

An actual Pesapal adapter is intentionally not fabricated. It should be added only with the merchant's authorized Pesapal API credentials and tested against Pesapal's official sandbox/production contract.

## South Africa

### Capitec pattern

Core problem solved: bank-account money becomes convenient at online checkout and physical merchants through app approval, scan-to-pay and virtual cards.

Engineering lessons:

- Give customers channel controls: online, contactless, international.
- Spending limits should be enforceable independently of the merchant UI.
- QR is a reusable initiation channel.

Repository implementation:

- `VirtualCardControls` operates only on a token reference, never PAN/CVV.
- Dynamic QR and provider-neutral QR intents.
- `RiskEngine` for pre-provider limits/velocity.

### FNB pattern

Core problem solved: make account holders able to send money to a phone number, use virtual cards/digital wallets, scan QR, make checkout payments, and let merchants accept without dedicated hardware.

Repository implementation:

- alias addressing.
- payment links and QR.
- card policy controls.
- agent/cash concepts remain separate from digital transfer intent.

### Standard Bank / Absa pattern

Core problem solved: mature bank rails packaged into a mobile experience with transfers, cards, virtual-card controls, QR/contactless and account management.

Repository implication: bank/card rails are represented as `BANK_TRANSFER` and `CARD`, but no bank/issuer is impersonated. Authorized bank or acquirer adapters register against the same provider contract.

### Google Wallet pattern

Core problem solved: tokenized card credentials from supported issuers become a device-level tap-to-pay instrument.

Repository boundary:

- This SDK does not tokenize cards or emulate Google Wallet.
- `VirtualCardControls` stores a **token reference** only and can enforce application-side rules around a token issued by an authorized processor/issuer.
- NFC provisioning, network tokenization, device attestation and issuer verification belong to the wallet/network/issuer integrations.

## Egypt

### InstaPay / IPN pattern

Core problem solved: instant 24/7 transfers across multiple bank accounts, cards and mobile wallets, addressed by account/IBAN, mobile number or Instant Payment Address, with balance/statement visibility and bills.

Architecture learned from it:

- Human-readable aliases must resolve to a destination before the payment is routed.
- Alias ownership must not be silently overwritten.
- The user should be able to select a funding source independently of the beneficiary alias.

Repository implementation:

- `PaymentAlias` and `AliasDirectory`.
- `PaymentPlatform::payAlias()` combines alias resolution with ordinary routing/risk/idempotency.
- Bank/wallet/card are distinct `PaymentMethod` values.

### myfawry pattern

Core problem solved: aggregate a large bill/service catalogue behind one payment experience, including one-time and recurring consumer obligations.

Repository implementation:

- `Biller` carries currency, payee, account-reference validation, min/max amount and arbitrary metadata.
- `BillerCatalog` provides discovery.
- `BillPaymentService` turns the bill into an ordinary orchestrated payment.

Recurring scheduling itself belongs to the host application's scheduler because execution authorization and retry policy are business-specific.

### Thndr pattern

Core problem solved: make the difference between account value and withdrawable cash explicit. Securities proceeds can be settled, unsettled or pending.

Repository implementation:

- `SettlementWallet` maintains spendable/settled value separately from pending settlement references and only permits debit from settled funds.
- This same primitive is useful for marketplace seller payouts, card acquiring, reserve periods and delayed merchant settlement — not only investments.

### Banque Misr / NBE mobile-banking pattern

Core problem solved: combine account transfer, bills, card/account services and transaction history in a regulated bank app.

Repository implication: the payment kernel must be ledgered, reconcilable and provider-neutral rather than tied to one checkout screen.

## Intertwined end-to-end flows

### 1. Mobile-money ecommerce checkout

1. Host app creates a `PaymentRequest` with `Money`, payer, merchant, reference and idempotency key.
2. `PaymentPlatform` calls `PaymentOrchestrator`.
3. `RiskEngine` evaluates amount/velocity.
4. `PaymentRouter` selects an authorized compatible provider.
5. `FileIdempotencyStore` serializes duplicate same-key execution.
6. `DarajaProvider` obtains/caches OAuth and initiates STK Push.
7. Initial response is `PENDING`.
8. Callback is source-checked and normalized by `TransactionCallbacks`.
9. Host persists the provider receipt/status transactionally.
10. `recordSuccessfulPayment()` can post internal clearing → merchant journal entries and award rewards.
11. Reconciliation later compares provider and internal records.

### 2. Payment link / request-to-pay

1. `PaymentLinkService` creates HMAC-signed amount/reference/merchant metadata with an expiry.
2. Customer opens the URL through the host application.
3. Host verifies signature/expiry.
4. `PaymentPlatform::collectPaymentLink()` creates the ordinary payment intent.
5. The same risk, idempotency, routing, callback and ledger flow applies.

This is the architectural bridge between Pesapal-style payment links and M-PESA STK collection: the link is an initiation channel; M-PESA remains the authorized settlement rail.

### 3. Alias transfer

1. Merchant/user registers an alias such as `@gan-store` or a phone identifier.
2. `AliasDirectory` prevents conflicting alias ownership.
3. `PaymentPlatform::payAlias()` resolves the destination and constructs a regular payment request.
4. Router picks the provider appropriate to currency/method.

This imports InstaPay's addressing idea without pretending M-PESA itself understands an InstaPay IPA.

### 4. Bill payment

1. Biller is registered with reference regex, currency and limits.
2. User supplies bill account/reference and amount.
3. Biller validates before any external network call.
4. Payment is routed/idempotently initiated.
5. Provider result and eventual bill fulfillment can be reconciled separately.

### 5. Agent cash-in / cash-out

1. Agent starts with physical cash and electronic float.
2. Cash-in decreases e-float and increases physical cash after the regulated transfer succeeds.
3. Cash-out increases e-float and decreases physical cash after the regulated transfer succeeds.
4. The same transaction ID cannot be replayed with different details.

This models MoMo/OPay/M-PESA agent economics without claiming the library itself can legally or physically hand over cash.

### 6. Merchant settlement / investment-style availability

1. Provider/acquirer callback credits a pending settlement position if funds are not yet withdrawable.
2. `settleDue()` moves eligible pending entries to settled.
3. Only settled balance can be debited.
4. Merchant dashboard can show total vs spendable balance honestly.

## What requires external authorization before it can be implemented as a live adapter

The following are deliberately **integration boundaries**, not missing fake code:

- card acquiring and raw authorization messages,
- card issuing / PAN / CVV generation,
- Google Wallet token provisioning,
- direct OPay/PalmPay/Moniepoint APIs,
- Pesapal merchant API,
- Airtel Money merchant/disbursement APIs,
- Egyptian IPN/InstaPay participant interfaces,
- South African bank/acquirer APIs,
- bank core account access.

Each needs an agreement/credential/certificate/issuer or acquirer relationship. When such access exists, implement `PaymentProvider` (and a payout-specific adapter where needed), register it with `PaymentPlatform`, and keep the rest of the journeys unchanged.

## Why this architecture is preferable to copying every app

The payment products in the screenshot differ by regulation and market structure, but repeatedly converge on the same primitives: identity/addressing, initiation, authorization, ledgering, settlement, status, reconciliation, liquidity, risk, rewards and merchant tooling. Keeping those primitives explicit prevents the M-PESA repository from becoming a brittle collection of hard-coded third-party API calls.
