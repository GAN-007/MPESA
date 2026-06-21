<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Payments;

final readonly class PaymentIntentData
{
    public function __construct(
        public string $merchantId,
        public int $amountMinor,
        public string $currency,
        public string $phoneNumber,
        public ?string $merchantReference,
        public ?string $description,
        public array $metadata = [],
    ) {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \InvalidArgumentException('Currency must be a valid ISO 4217 code.');
        }
    }
}
