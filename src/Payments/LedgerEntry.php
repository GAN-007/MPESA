<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Money;

final class LedgerEntry
{
    public function __construct(
        public readonly string $account,
        public readonly Money $debit,
        public readonly Money $credit,
        public readonly array $metadata = [],
    ) {
        if ($debit->currency !== $credit->currency || $debit->scale !== $credit->scale) throw PaymentException::validation('Ledger debit and credit values must use the same currency and scale.');
        if ($debit->minor > 0 && $credit->minor > 0) throw PaymentException::validation('A ledger entry cannot be both a debit and a credit.');
        if ($debit->minor === 0 && $credit->minor === 0) throw PaymentException::validation('A ledger entry must contain a debit or a credit.');
    }
}
