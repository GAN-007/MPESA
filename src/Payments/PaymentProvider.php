<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

interface PaymentProvider
{
    public function name(): string;
    public function supports(PaymentRequest $request): bool;
    public function collect(PaymentRequest $request): PaymentResult;
    public function query(string $providerReference, array $context = []): PaymentResult;
}
