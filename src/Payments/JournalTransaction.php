<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class JournalTransaction
{
    /** @param list<LedgerEntry> $entries */
    public function __construct(
        public readonly string $id,
        public readonly string $reference,
        public readonly array $entries,
        public readonly int $createdAt,
        public readonly array $metadata = [],
    ) {
        if ($entries === []) throw PaymentException::validation('A journal transaction requires at least two entries.');
        $currency = $entries[0]->debit->currency;
        $scale = $entries[0]->debit->scale;
        $debits = 0; $credits = 0;
        foreach ($entries as $entry) {
            if ($entry->debit->currency !== $currency || $entry->debit->scale !== $scale) throw PaymentException::validation('All ledger entries must use the same currency and scale.');
            $debits += $entry->debit->minor; $credits += $entry->credit->minor;
        }
        if ($debits !== $credits) throw PaymentException::validation('Journal transaction is not balanced.');
    }
}
