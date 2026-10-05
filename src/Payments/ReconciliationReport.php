<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

final class ReconciliationReport
{
    public function __construct(
        public readonly array $matched,
        public readonly array $missingAtProvider,
        public readonly array $missingInternally,
        public readonly array $mismatches,
    ) {}

    public function clean(): bool
    {
        return $this->missingAtProvider === [] && $this->missingInternally === [] && $this->mismatches === [];
    }
}
