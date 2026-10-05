<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class RewardsEngine
{
    /** @var array<string, int> */
    private array $points = [];

    public function earn(string $customerId, int $amountMinor, int $basisPoints = 100): int
    {
        if ($customerId === '' || $amountMinor < 0 || $basisPoints < 0) throw PaymentException::validation('Invalid rewards input.');
        $earned = intdiv($amountMinor * $basisPoints, 10000);
        $this->points[$customerId] = ($this->points[$customerId] ?? 0) + $earned;
        return $earned;
    }

    public function redeem(string $customerId, int $points): void
    {
        if ($points <= 0 || ($this->points[$customerId] ?? 0) < $points) throw PaymentException::validation('Insufficient reward points.');
        $this->points[$customerId] -= $points;
    }

    public function balance(string $customerId): int { return $this->points[$customerId] ?? 0; }
}
