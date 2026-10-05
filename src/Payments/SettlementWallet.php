<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Money;

final class SettlementWallet
{
    private int $settledMinor = 0;
    /** @var array<string, array{minor:int, available_at:int}> */
    private array $pending = [];

    public function __construct(
        public readonly string $id,
        public readonly string $currency = 'KES',
        public readonly int $scale = 2,
    ) {}

    public function creditSettled(Money $amount): void
    {
        $this->assertMoney($amount);
        $this->settledMinor += $amount->minor;
    }

    public function creditPending(string $reference, Money $amount, int $availableAt): void
    {
        $this->assertMoney($amount);
        if ($availableAt < time() - 86400) throw PaymentException::validation('Pending settlement date is invalid.');
        if (isset($this->pending[$reference])) {
            if ($this->pending[$reference]['minor'] !== $amount->minor || $this->pending[$reference]['available_at'] !== $availableAt) {
                throw PaymentException::validation('Pending settlement reference collision.');
            }
            return;
        }
        $this->pending[$reference] = ['minor' => $amount->minor, 'available_at' => $availableAt];
    }

    public function settleDue(?int $now = null): int
    {
        $now ??= time();
        $settled = 0;
        foreach ($this->pending as $reference => $item) {
            if ($item['available_at'] <= $now) {
                $settled += $item['minor'];
                $this->settledMinor += $item['minor'];
                unset($this->pending[$reference]);
            }
        }
        return $settled;
    }

    public function debit(Money $amount): void
    {
        $this->assertMoney($amount);
        if ($amount->minor > $this->settledMinor) throw PaymentException::validation('Insufficient settled funds.');
        $this->settledMinor -= $amount->minor;
    }

    public function position(): SettlementPosition
    {
        $pendingMinor = array_sum(array_column($this->pending, 'minor'));
        return new SettlementPosition(
            new Money($this->settledMinor, $this->currency, $this->scale),
            new Money($pendingMinor, $this->currency, $this->scale),
        );
    }

    private function assertMoney(Money $amount): void
    {
        if ($amount->currency !== $this->currency || $amount->scale !== $this->scale) {
            throw PaymentException::validation('Wallet and amount currency/scale mismatch.');
        }
    }
}
