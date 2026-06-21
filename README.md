# GAN-007 MPESA PHP SDK

Production-ready PHP 8.1+ SDK for Safaricom M-PESA Daraja APIs.

This branch modernizes the original package with Composer metadata, PSR-4 source files, secure HTTP defaults, typed configuration, safer callback parsing, tests, and CI.

## Install

```bash
composer require gan-007/mpesa
```

## Configure

Copy `.env.example` into your application environment and set your Daraja credentials outside source control.

Required variables:

- `MPESA_ENV` as `sandbox` or `live`
- `MPESA_CONSUMER_KEY`
- `MPESA_CONSUMER_SECRET`
- `MPESA_SHORTCODE`
- `MPESA_PASSKEY`

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Safaricom\Mpesa\Mpesa;

$mpesa = new Mpesa();
$response = $mpesa->stkPush(10, '0712345678', 'https://app.example.test/mpesa/stk', 'INV-1001', 'Invoice payment');

print_r($response->data());
```

## Callback parsing

```php
use Safaricom\Mpesa\TransactionCallbacks;

$payload = TransactionCallbacks::processSTKPushRequestCallback(asJson: false);
```

## Production notes

Keep SSL verification enabled, never commit real credentials, use HTTPS callback URLs, and persist all checkout, conversation, transaction, and receipt identifiers for reconciliation.

Maintained by George Alfred Nyamema / GAN-007.
