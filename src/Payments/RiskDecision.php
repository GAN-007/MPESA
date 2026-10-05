<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

final class RiskDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $score,
        public readonly array $reasons,
    ) {}
}
