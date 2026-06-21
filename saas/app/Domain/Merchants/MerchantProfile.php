<?php

declare(strict_types=1);

namespace GANTech\Payments\Domain\Merchants;

final readonly class MerchantProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $legalName,
        public string $country = 'KE',
        public string $defaultCurrency = 'KES',
        public string $status = 'pending',
        public string $verificationStatus = 'unverified',
    ) {
    }
}
