<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class PaymentOrchestrator
{
    public function __construct(
        private readonly PaymentRouter $router,
        private readonly RiskEngine $risk,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function collect(PaymentRequest $request, string $idempotencyKey): PaymentResult
    {
        if (trim($idempotencyKey) === '') {
            throw PaymentException::validation('An idempotency key is required for collection attempts.');
        }

        $fingerprint = $request->fingerprint();
        return $this->idempotency->remember($idempotencyKey, $fingerprint, function () use ($request): PaymentResult {
            $risk = $this->risk->evaluate($request);
            if (!$risk->allowed) {
                return new PaymentResult(
                    paymentId: $request->id,
                    provider: 'risk-engine',
                    status: PaymentStatus::FAILED,
                    customerMessage: 'Payment rejected by configured risk controls.',
                    raw: ['risk_score' => $risk->score, 'reasons' => $risk->reasons],
                );
            }

            $provider = $this->router->route($request);
            return $provider->collect($request);
        });
    }
}
