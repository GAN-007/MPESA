<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class ReconciliationItem
{
    public function __construct(
        public readonly string $providerReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $status,
        public readonly ?string $internalReference = null,
        public readonly array $metadata = [],
    ) {
        if ($providerReference === '' || $amountMinor < 0 || !preg_match('/^[A-Z]{3}$/', $currency) || $status === '') {
            throw PaymentException::validation('Invalid reconciliation item.');
        }
    }
}
