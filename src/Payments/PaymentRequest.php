<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Money;
use Safaricom\Mpesa\Support\Ids;

final class PaymentRequest
{
    public readonly string $id;

    public function __construct(
        public readonly Money $amount,
        public readonly PaymentMethod $method,
        public readonly string $payer,
        public readonly string $payee,
        public readonly string $reference,
        public readonly string $description,
        public readonly array $metadata = [],
        ?string $id = null,
    ) {
        $this->id = $id ?? Ids::payment();
        if ($this->payer === '' || $this->payee === '') {
            throw PaymentException::validation('Payer and payee are required.');
        }
        if ($this->reference === '') {
            throw PaymentException::validation('Payment reference is required.');
        }
        if (strlen($this->reference) > 64) {
            throw PaymentException::validation('Payment reference must not exceed 64 characters.');
        }
        if (strlen($this->description) > 160) {
            throw PaymentException::validation('Payment description must not exceed 160 characters.');
        }
    }

    public function fingerprint(): string
    {
        return Ids::idempotency('payment-request', [
            $this->amount->minor,
            $this->amount->currency,
            $this->method->value,
            $this->payer,
            $this->payee,
            $this->reference,
        ]);
    }
}
