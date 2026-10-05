# Migration from the original package

The repository originally consisted of five root files and implemented Daraja requests directly with repeated cURL blocks. This revision preserves the public namespace and legacy method names while moving implementation under PSR-4 `src/` classes.

## Runtime baseline

The modern implementation requires PHP 8.2+. The previous Composer manifest allowed PHP 5.3, which is no longer an appropriate security/runtime baseline for a payment SDK.

## Composer / autoload

The old manifest declared `Safaricom\Mpesa\ => src/` while the PHP classes were stored in the repository root. The classes now exist under `src/`, and the root files are compatibility shims.

## Environment loading

The old implementation loaded `../.env` through Symfony Dotenv even though `symfony/dotenv` was only a development dependency. The new `Env` helper reads an optional local `.env` without requiring a framework package and always respects already-defined process environment variables.

Production secrets should come from the deployment environment/secret manager, not a committed `.env` file.

## HTTP and TLS

Removed behavior:

```php
CURLOPT_SSL_VERIFYPEER => false
```

TLS certificate and hostname verification are now mandatory in both the cURL and stream transports.

HTTP failures now surface as `PaymentException`; responses are parsed consistently; transient `429` and `5xx` failures are retried using bounded exponential backoff; one `401` triggers token refresh.

## B2C

B2C now uses Daraja v3 (`/mpesa/b2c/v3/paymentrequest`). The current API requires an `OriginatorConversationID`, so the provider generates a compact value when a legacy caller does not supply one. A host application with a durable payout ID should pass its own value.

The Daraja request field is currently spelled `Occassion`. The legacy wrapper accepts its historical `Occasion` argument and maps it to the provider spelling.

## STK Push

The old method accepted `$TransactionDesc` but sent `$TransactionType` in the `TransactionDesc` field. The new method sends the actual description.

`STKPushQuery()` still accepts the old 4/5-argument form and also supports querying by CheckoutRequestID using configured shortcode/passkey.

## C2B callbacks

The old parser accidentally used callback **values as JSON object keys**. The new parser emits stable field names such as `transID`, `transAmount`, `businessShortCode`, `billRefNumber` and `MSISDN`.

## STK callbacks

The old implementation mixed `Body->stkCallback` and `stkCallback->Body` paths and assumed metadata array order. The new parser indexes `CallbackMetadata.Item` by each item's `Name`, so amount, receipt, date and phone are not tied to a fixed index.

## B2B/B2C/status/balance result callbacks

The old implementation read `ResultParameter[0]`, `[1]`, etc. The current implementation indexes by `Key` and returns:

- common normalized fields,
- a complete `resultParameters` map,
- a complete `referenceData` map,
- exact parameter keys flattened for compatibility,
- lower-first aliases such as `transactionReceipt` and `transactionAmount`.

## Return values

Modern `DarajaProvider` methods return PHP arrays. The `Mpesa` compatibility facade keeps the historical JSON-string/object shapes where practical.

## Laravel

Laravel auto-discovery is declared in Composer metadata. The service provider binds:

- `MpesaConfig`,
- `DarajaProvider`,
- legacy `Mpesa`,
- `ProviderRegistry`,
- durable `IdempotencyStore`,
- `PaymentOrchestrator`,
- `PaymentPlatform`.

The default Laravel idempotency path is under `storage/framework/cache/mpesa-idempotency`.
