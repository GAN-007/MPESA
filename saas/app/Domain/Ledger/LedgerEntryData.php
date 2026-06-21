<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Ledger;

final readonly class LedgerEntryData
{
    public function __construct(
        public string $merchantId,
        public string $ledgerAccountId,
        public LedgerDirection $direction,
        public int $amountMinor,
        public string $currency,
        public string $description,
        public array $metadata = [],
    ) {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Ledger amount must be greater than zero.');
        }
    }
}
