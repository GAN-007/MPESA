# M-PESA / Daraja PHP SDK + African Payment Orchestration Core

This repository keeps the original `Safaricom\Mpesa` public surface, but replaces the 2017-era transport and callback implementation with a PHP 8.2+ Daraja 3 compatible core and a provider-neutral payment orchestration layer.

The design is intentionally broader than a single STK Push helper. It learns from the strongest payment journeys visible across African mobile-money, bank, merchant-acquiring, QR, bill-pay, instant-payment, virtual-card and settlement products, while keeping **M-PESA/Daraja as the real, implemented network adapter in this repository**.

Direct integrations to OPay, PalmPay, Moniepoint, Airtel Money, InstaPay, Pesapal, Google Wallet, Capitec, FNB, Absa, Thndr, Fawry, Equity or NCBA are **not faked**. Those providers require their own commercial/API credentials, contracts, certificates or issuer/acquirer access. Their useful product patterns are implemented here as reusable primitives behind `PaymentProvider`, so an authorized adapter can be added without rewriting checkout, risk, idempotency, ledger, payment-link, biller or reconciliation logic.

## What is implemented

### Daraja / M-PESA

`Safaricom\Mpesa\Providers\Mpesa\DarajaProvider` implements:

- OAuth access-token generation and in-process token caching.
- M-PESA Express / STK Push and STK query.
- C2B URL registration and sandbox C2B simulation.
- B2C v3 disbursements with merchant-generated `OriginatorConversationID`.
- B2B, Business Pay Bill and Business Buy Goods.
- Transaction Status.
- Account Balance.
- Reversal.
- Tax Remittance.
- Dynamic QR.
- B2B Express Checkout.
- Pull Transactions.
- Bill Manager operations.
- RSA PKCS#1 v1.5 security-credential encryption using the Safaricom X.509 public certificate.
- Safe retry of transient `429`/`5xx` responses and one forced token refresh on `401`.
- TLS peer and hostname verification. The old package's `CURLOPT_SSL_VERIFYPEER=false` behavior is gone.

### Cross-rail platform capabilities

The payment core adds:

- `PaymentProvider`, `ProviderRegistry`, `PaymentRouter` and `PaymentOrchestrator` for provider-neutral routing.
- Atomic idempotency through `IdempotencyStore`, with both in-memory and durable `FileIdempotencyStore` implementations.
- Risk and velocity controls with `RiskEngine`.
- Signed, expiring HMAC payment links with `PaymentLinkService`.
- InstaPay-style aliases through `AliasDirectory` (username, mobile, merchant alias, or another application-defined type).
- Biller catalogue and bill-payment validation through `BillerCatalog` and `BillPaymentService`.
- Double-entry journal primitives through `Ledger` and `JournalTransaction`.
- Settled vs pending balances through `SettlementWallet`, modeled after securities/payment settlement behavior.
- Agent cash/float liquidity with `AgentNetwork`, covering cash-in/cash-out economics without pretending the SDK itself operates a regulated agent network.
- Token-reference-only virtual-card policy controls with `VirtualCardControls` (freeze, daily limits, online/contactless/international controls and merchant rules). The SDK never stores PAN/CVV.
- Rewards/cashback points through `RewardsEngine`.
- Provider/internal reconciliation with `ReconciliationService`.
- Safaricom callback source-IP allow-list support through `CallbackSecurity`.
- A high-level `PaymentPlatform` composition root that intertwines aliases, payment links, billers, routing, idempotency, risk, ledger, rewards, agent liquidity, settlement and reconciliation.

## Requirements

- PHP 8.2+
- JSON extension
- OpenSSL extension
- cURL is recommended; a TLS-validating PHP stream transport is used when cURL is unavailable.
- Laravel is optional. `illuminate/support` is only needed when using `MpesaServiceProvider`.

## Installation

From a checkout of this repository:

```bash
composer install
composer check
```

When this package is installed through Composer, PSR-4 autoloading maps `Safaricom\Mpesa\` to `src/`.

Copy `.env.example` to `.env` only for local development and supply your own Daraja credentials. Never commit production keys, initiator passwords, security credentials or passkeys.

## Configuration

```dotenv
MPESA_ENV=sandbox
MPESA_CONSUMER_KEY=your_daraja_consumer_key
MPESA_CONSUMER_SECRET=your_daraja_consumer_secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your_lipa_na_mpesa_passkey
MPESA_INITIATOR=your_api_operator
MPESA_SECURITY_CREDENTIAL=your_rsa_encrypted_operator_password
MPESA_CALLBACK_URL=https://your-public-host.example/payments/mpesa/stk
MPESA_HTTP_TIMEOUT=30
MPESA_HTTP_CONNECT_TIMEOUT=10
MPESA_HTTP_RETRIES=2

PAYMENTS_LINK_BASE_URL=https://pay.your-domain.example
PAYMENTS_LINK_SECRET=use-a-cryptographically-random-secret-of-at-least-32-characters
```

`MPESA_SECURITY_CREDENTIAL` is not the plaintext initiator password. Use `DarajaProvider::encryptSecurityCredential()` with the correct Safaricom sandbox or production certificate, or generate the value using the Daraja portal.

## Modern M-PESA checkout

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Safaricom\Mpesa\Config\MpesaConfig;
use Safaricom\Mpesa\Payments\PaymentMethod;
use Safaricom\Mpesa\Payments\PaymentRequest;
use Safaricom\Mpesa\Providers\Mpesa\DarajaProvider;
use Safaricom\Mpesa\Support\Money;

$config = MpesaConfig::fromEnvironment();
$mpesa = new DarajaProvider($config);

$result = $mpesa->collect(new PaymentRequest(
    amount: Money::fromDecimal('1250.00', 'KES'),
    method: PaymentMethod::MOBILE_MONEY,
    payer: '0712345678',
    payee: $config->shortCode ?? throw new RuntimeException('MPESA_SHORTCODE is required'),
    reference: 'ORDER-2026-000184',
    description: 'Order 2026-000184',
));

printf(
    "status=%s checkout=%s message=%s\n",
    $result->status->value,
    $result->providerReference ?? '',
    $result->customerMessage ?? '',
);
```

M-PESA Express is asynchronous. A successful initial API acknowledgement is **pending**, not proof that the customer paid. Complete the order only after a successful callback or a later status query.

## Production-oriented orchestration

The `PaymentPlatform` is the recommended application-level entry point when you need more than a raw Daraja call.

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Safaricom\Mpesa\Config\MpesaConfig;
use Safaricom\Mpesa\Payments\Biller;
use Safaricom\Mpesa\Payments\FileIdempotencyStore;
use Safaricom\Mpesa\Payments\PaymentAlias;
use Safaricom\Mpesa\Payments\PaymentLinkService;
use Safaricom\Mpesa\Payments\PaymentPlatform;
use Safaricom\Mpesa\Providers\Mpesa\DarajaProvider;
use Safaricom\Mpesa\Support\Money;

$config = MpesaConfig::fromEnvironment();
$mpesa = new DarajaProvider($config);
$idempotency = new FileIdempotencyStore(__DIR__ . '/var/mpesa-idempotency');
$links = new PaymentLinkService(
    getenv('PAYMENTS_LINK_BASE_URL') ?: throw new RuntimeException('PAYMENTS_LINK_BASE_URL is required'),
    getenv('PAYMENTS_LINK_SECRET') ?: throw new RuntimeException('PAYMENTS_LINK_SECRET is required'),
);

$payments = new PaymentPlatform([$mpesa], $idempotency, paymentLinks: $links);
$payments->preferProvider('mpesa', 100);

$payments->aliases->register(new PaymentAlias(
    alias: '@gan-store',
    type: 'merchant',
    destination: $config->shortCode ?? throw new RuntimeException('MPESA_SHORTCODE is required'),
    currency: 'KES',
    metadata: ['party_b' => $config->shortCode],
));

$payments->billers->register(new Biller(
    id: 'merchant-account',
    name: 'Merchant account payment',
    payee: $config->shortCode,
    currency: 'KES',
    referencePattern: '/^[A-Z0-9-]{4,32}$/',
));

$link = $payments->createPaymentLink(
    merchant: $config->shortCode,
    amount: Money::fromDecimal('850.00', 'KES'),
    reference: 'INV-2026-0009',
    ttlSeconds: 3600,
    metadata: ['shortcode' => $config->shortCode],
);

echo $link->url . PHP_EOL;
```

For a multi-node application, implement `IdempotencyStore` against a transactional shared database or Redis. `FileIdempotencyStore` is durable and process-safe on one shared filesystem, but cannot provide distributed locking across unrelated filesystems.

## Dynamic QR

```php
$response = $mpesa->dynamicQr([
    'MerchantName' => 'GAN Store',
    'RefNo' => 'ORDER-184',
    'Amount' => 500,
    'TrxCode' => 'PB',
    'CPI' => '600000',
    'Size' => '300',
]);
```

Supported M-PESA dynamic-QR transaction codes in this SDK are `BG`, `WA`, `PB`, `SM`, and `SB`.

## C2B registration

```php
$response = $mpesa->registerC2bUrls(
    shortCode: '600000',
    confirmationUrl: 'https://merchant.example/mpesa/c2b/confirmation',
    validationUrl: 'https://merchant.example/mpesa/c2b/validation',
    responseType: 'Completed',
);
```

C2B simulation is deliberately rejected outside the sandbox.

## B2C v3

```php
$response = $mpesa->b2c([
    'InitiatorName' => getenv('MPESA_INITIATOR'),
    'SecurityCredential' => getenv('MPESA_SECURITY_CREDENTIAL'),
    'CommandID' => 'BusinessPayment',
    'Amount' => 100,
    'PartyA' => getenv('MPESA_SHORTCODE'),
    'PartyB' => '254712345678',
    'Remarks' => 'Customer refund',
    'QueueTimeOutURL' => 'https://merchant.example/mpesa/b2c/timeout',
    'ResultURL' => 'https://merchant.example/mpesa/b2c/result',
    'Occassion' => 'Refund',
]);
```

If `OriginatorConversationID` is omitted, the SDK creates a compact merchant-generated ID. Supplying your own stable ID is preferable when the value is already tied to a durable payout record.

## Transaction status as a reconciliation fallback

```php
$response = $mpesa->transactionStatus([
    'Initiator' => getenv('MPESA_INITIATOR'),
    'SecurityCredential' => getenv('MPESA_SECURITY_CREDENTIAL'),
    'CommandID' => 'TransactionStatusQuery',
    'TransactionID' => 'SG632NMUAB',
    'PartyA' => getenv('MPESA_SHORTCODE'),
    'IdentifierType' => '4',
    'ResultURL' => 'https://merchant.example/mpesa/status/result',
    'QueueTimeOutURL' => 'https://merchant.example/mpesa/status/timeout',
    'Remarks' => 'reconcile',
    'Occasion' => 'nightly-reconciliation',
]);
```

The method also accepts `OriginalConversationID` instead of `TransactionID`.

## Callbacks

The callback parser is order-independent. It indexes Daraja `ResultParameter` entries by their `Key`, rather than assuming the first item is always the amount, receipt or balance.

```php
use Safaricom\Mpesa\Support\CallbackSecurity;
use Safaricom\Mpesa\TransactionCallbacks;

CallbackSecurity::assertAllowedDarajaIp($_SERVER['REMOTE_ADDR'] ?? '');

$normalized = json_decode(
    TransactionCallbacks::processSTKPushRequestCallback(),
    true,
    512,
    JSON_THROW_ON_ERROR,
);

if ((int) $normalized['resultCode'] === 0) {
    // Persist receipt, amount, phone and checkoutRequestID in one DB transaction.
}
```

If your app is behind a reverse proxy, validate the proxy itself and obtain the client IP from a forwarding header **only after your edge has stripped untrusted client-supplied forwarding headers**.

Safaricom documents that callbacks are asynchronous. Your endpoint should be publicly reachable, fast, idempotent and capable of accepting retries/out-of-order delivery at the application layer.

## Legacy compatibility

The original root files remain as shims:

- `Mpesa.php`
- `TransactionCallbacks.php`
- `MpesaServiceProvider.php`

The original method names (`generateLiveToken`, `generateSandBoxToken`, `reversal`, `b2c`, `c2b`, `accountBalance`, `transactionStatus`, `b2b`, `STKPushSimulation`, `STKPushQuery`, `finishTransaction`, `getDataFromCallback`) remain available through `Safaricom\Mpesa\Mpesa`.

The implementation fixes several historical bugs without keeping their incorrect behavior: TLS verification is enabled; C2B callback fields have stable names; STK metadata uses the correct callback path and key names; `TransactionDesc` is no longer replaced by the transaction type; B2C is updated to v3; and responses are consistently decoded before being exposed by the modern API.

See [`docs/MIGRATION.md`](docs/MIGRATION.md) for behavior changes.

## Tests

The test suite is dependency-free and uses an in-memory fake HTTP client. It never calls Safaricom or a live payment network.

```bash
php tests/lint.php
php tests/run.php
```

The suite covers money precision, MSISDN normalization, signed links, aliases, double-entry ledger behavior, settlement, card policy controls, rewards, idempotent routing, durable idempotency, agent liquidity, reconciliation, intertwined `PaymentPlatform` journeys, B2C v3 request construction, token caching/refresh, callback source validation, and robust callback parsing.

## Security boundaries

This library does not make an application PCI DSS compliant, does not issue cards, does not create a bank/mobile-money licence, and does not replace merchant KYC/AML, sanctions screening, transaction monitoring, fraud operations, customer dispute handling or legal/regulatory obligations.

For card journeys, store only processor/issuer token references. Never place PAN, CVV, PIN, OTP or raw card-track data in `PaymentRequest::metadata`, logs or this SDK's ledger.

For production M-PESA callbacks, combine source-IP allow-listing with HTTPS, application-level idempotency, strict schema validation, private network controls where available, and reconciliation using Transaction Status/Pull Transactions.

See [`SECURITY.md`](SECURITY.md).

## Architecture research

The product-by-product analysis that drove the new abstractions is in [`docs/AFRICA_PAYMENT_ARCHITECTURE.md`](docs/AFRICA_PAYMENT_ARCHITECTURE.md). It maps the screenshot's systems and user journeys to concrete classes in this repository and separates product inspiration from integrations that require third-party authorization.

## Official Daraja references

- Daraja API catalogue: https://developer.safaricom.co.ke/apis
- Getting started and callback allow-list: https://developer.safaricom.co.ke/apis/GettingStarted
- B2C: https://developer.safaricom.co.ke/apis/BusinessToCustomer
- C2B: https://developer.safaricom.co.ke/apis/CustomerToBusiness
- Transaction Status: https://developer.safaricom.co.ke/apis/TransactionStatus

## License

MIT. See [`LICENSE`](LICENSE).
