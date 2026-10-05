<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

interface IdempotencyStore
{
    public function get(string $key): ?PaymentResult;
    public function put(string $key, string $fingerprint, PaymentResult $result): void;
    public function assertSameRequest(string $key, string $fingerprint): void;

    /**
     * Execute a payment-producing callback at most once for a key inside this store's
     * concurrency boundary, returning the previously stored result on replays.
     *
     * @param callable():PaymentResult $producer
     */
    public function remember(string $key, string $fingerprint, callable $producer): PaymentResult;
}
