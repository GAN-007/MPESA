<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Money;

final class Agent
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $currency,
        public int $electronicFloatMinor,
        public int $cashMinor,
        public readonly array $metadata = [],
    ) {
        if ($id === '' || $name === '') throw PaymentException::validation('Agent id and name are required.');
        if (!preg_match('/^[A-Z]{3}$/', $currency)) throw PaymentException::validation('Agent currency must be ISO 4217 format.');
        if ($electronicFloatMinor < 0 || $cashMinor < 0) throw PaymentException::validation('Agent float and cash balances cannot be negative.');
    }

    public function float(): Money { return new Money($this->electronicFloatMinor, $this->currency); }
    public function cash(): Money { return new Money($this->cashMinor, $this->currency); }
}
