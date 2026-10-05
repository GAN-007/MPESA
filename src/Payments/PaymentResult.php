<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

final class PaymentResult
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $provider,
        public readonly PaymentStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $customerMessage = null,
        public readonly array $raw = [],
    ) {}
}
