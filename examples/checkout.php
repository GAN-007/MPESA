<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Safaricom\Mpesa\Config\MpesaConfig;
use Safaricom\Mpesa\Payments\FileIdempotencyStore;
use Safaricom\Mpesa\Payments\PaymentMethod;
use Safaricom\Mpesa\Payments\PaymentPlatform;
use Safaricom\Mpesa\Payments\PaymentRequest;
use Safaricom\Mpesa\Providers\Mpesa\DarajaProvider;
use Safaricom\Mpesa\Support\Money;

$config = MpesaConfig::fromEnvironment();
$provider = new DarajaProvider($config);
$platform = new PaymentPlatform(
    [$provider],
    new FileIdempotencyStore(dirname(__DIR__) . '/var/idempotency'),
);
$platform->preferProvider('mpesa', 100);

$phone = $argv[1] ?? throw new InvalidArgumentException('Usage: php examples/checkout.php 0712345678 125.00 ORDER-123');
$amount = $argv[2] ?? throw new InvalidArgumentException('Amount argument is required.');
$reference = $argv[3] ?? throw new InvalidArgumentException('Reference argument is required.');
$shortCode = $config->shortCode ?? throw new RuntimeException('MPESA_SHORTCODE is required.');

$request = new PaymentRequest(
    amount: Money::fromDecimal($amount, 'KES'),
    method: PaymentMethod::MOBILE_MONEY,
    payer: $phone,
    payee: $shortCode,
    reference: $reference,
    description: 'Checkout ' . $reference,
);

$result = $platform->collect($request, 'checkout:' . $reference);
echo json_encode([
    'payment_id' => $result->paymentId,
    'provider' => $result->provider,
    'status' => $result->status->value,
    'provider_reference' => $result->providerReference,
    'customer_message' => $result->customerMessage,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
