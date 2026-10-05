<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Money;

final class Biller
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $payee,
        public readonly string $currency = 'KES',
        public readonly ?string $referencePattern = null,
        public readonly int $minimumMinor = 1,
        public readonly ?int $maximumMinor = null,
        public readonly array $metadata = [],
    ) {
        if ($id === '' || $name === '' || $payee === '') throw PaymentException::validation('Biller id, name and payee are required.');
        if (!preg_match('/^[A-Z]{3}$/', $currency)) throw PaymentException::validation('Biller currency must be ISO 4217 format.');
    }

    public function validate(string $reference, Money $amount): void
    {
        if ($amount->currency !== $this->currency) throw PaymentException::validation('Biller does not accept the supplied currency.');
        if ($amount->minor < $this->minimumMinor) throw PaymentException::validation('Bill amount is below the biller minimum.');
        if ($this->maximumMinor !== null && $amount->minor > $this->maximumMinor) throw PaymentException::validation('Bill amount exceeds the biller maximum.');
        if ($this->referencePattern !== null && preg_match($this->referencePattern, $reference) !== 1) {
            throw PaymentException::validation('Bill reference does not match the biller format.');
        }
    }
}
