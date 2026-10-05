<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

final class PaymentAlias
{
    public function __construct(
        public readonly string $alias,
        public readonly string $type,
        public readonly string $destination,
        public readonly string $currency,
        public readonly array $metadata = [],
    ) {}
}
