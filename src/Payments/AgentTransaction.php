<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Support\Money;

final class AgentTransaction
{
    public function __construct(
        public readonly string $id,
        public readonly string $agentId,
        public readonly string $type,
        public readonly string $customer,
        public readonly Money $amount,
        public readonly int $createdAt,
        public readonly array $metadata = [],
    ) {}
}
