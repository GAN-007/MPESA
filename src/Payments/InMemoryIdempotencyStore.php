<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class InMemoryIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, array{fingerprint:string,result:PaymentResult}> */
    private array $items = [];

    public function get(string $key): ?PaymentResult
    {
        return $this->items[$key]['result'] ?? null;
    }

    public function put(string $key, string $fingerprint, PaymentResult $result): void
    {
        if (isset($this->items[$key])) {
            $this->assertSameRequest($key, $fingerprint);
            return;
        }
        $this->items[$key] = ['fingerprint' => $fingerprint, 'result' => $result];
    }

    public function assertSameRequest(string $key, string $fingerprint): void
    {
        if (isset($this->items[$key]) && !hash_equals($this->items[$key]['fingerprint'], $fingerprint)) {
            throw PaymentException::validation('The idempotency key was already used for a different payment request.');
        }
    }

    public function remember(string $key, string $fingerprint, callable $producer): PaymentResult
    {
        $this->assertSameRequest($key, $fingerprint);
        if (isset($this->items[$key])) {
            return $this->items[$key]['result'];
        }
        $result = $producer();
        if (!$result instanceof PaymentResult) {
            throw PaymentException::validation('Idempotency producer must return PaymentResult.');
        }
        $this->items[$key] = ['fingerprint' => $fingerprint, 'result' => $result];
        return $result;
    }
}
