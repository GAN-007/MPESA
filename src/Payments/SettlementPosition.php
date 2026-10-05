<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Support\Money;

final class SettlementPosition
{
    public function __construct(public readonly Money $settled, public readonly Money $pending) {}
    public function spendable(): Money { return $this->settled; }
    public function total(): Money { return $this->settled->add($this->pending); }
}
