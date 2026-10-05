<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Money;
use Safaricom\Mpesa\Support\Ids;

final class Ledger
{
    /** @var array<string, JournalTransaction> */
    private array $transactions = [];
    /** @var array<string, int> */
    private array $balances = [];

    public function post(JournalTransaction $transaction): void
    {
        if (isset($this->transactions[$transaction->id])) {
            if ($this->transactions[$transaction->id]->reference !== $transaction->reference) throw PaymentException::validation('Ledger transaction id collision.');
            return;
        }
        foreach ($transaction->entries as $entry) {
            $this->balances[$entry->account] = ($this->balances[$entry->account] ?? 0) + $entry->credit->minor - $entry->debit->minor;
        }
        $this->transactions[$transaction->id] = $transaction;
    }

    public function transfer(string $from, string $to, Money $amount, string $reference, ?string $id = null, array $metadata = []): JournalTransaction
    {
        if ($from === $to) throw PaymentException::validation('Ledger transfer requires different source and destination accounts.');
        $zero = new Money(0, $amount->currency, $amount->scale);
        $transaction = new JournalTransaction(
            id: $id ?? Ids::payment('jnl'),
            reference: $reference,
            entries: [
                new LedgerEntry($from, $amount, $zero, $metadata),
                new LedgerEntry($to, $zero, $amount, $metadata),
            ],
            createdAt: time(),
            metadata: $metadata,
        );
        $this->post($transaction);
        return $transaction;
    }

    public function balance(string $account): int { return $this->balances[$account] ?? 0; }
    public function transaction(string $id): ?JournalTransaction { return $this->transactions[$id] ?? null; }
}
