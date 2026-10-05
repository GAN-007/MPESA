<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Support\Money;

final class PaymentLink
{
    public function __construct(
        public readonly string $token,
        public readonly string $url,
        public readonly string $merchant,
        public readonly Money $amount,
        public readonly string $reference,
        public readonly int $expiresAt,
        public readonly array $metadata = [],
    ) {}
}
