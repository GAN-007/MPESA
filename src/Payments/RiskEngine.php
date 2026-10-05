<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

final class RiskEngine
{
    /** @var array<string, list<int>> */
    private array $payerEvents = [];

    public function __construct(
        private readonly int $singlePaymentMinorLimit = 50000000,
        private readonly int $velocityWindowSeconds = 300,
        private readonly int $velocityLimit = 6,
    ) {}

    public function evaluate(PaymentRequest $request, ?int $now = null): RiskDecision
    {
        $now ??= time();
        $score = 0;
        $reasons = [];

        if ($request->amount->minor > $this->singlePaymentMinorLimit) {
            $score += 90;
            $reasons[] = 'single_payment_limit_exceeded';
        }

        $events = $this->payerEvents[$request->payer] ?? [];
        $events = array_values(array_filter(
            $events,
            fn (int $timestamp): bool => $timestamp >= $now - $this->velocityWindowSeconds
        ));
        if (count($events) >= $this->velocityLimit) {
            $score += 70;
            $reasons[] = 'payer_velocity_exceeded';
        }

        if ($request->payer === $request->payee) {
            $score += 40;
            $reasons[] = 'payer_equals_payee';
        }

        $events[] = $now;
        $this->payerEvents[$request->payer] = $events;

        return new RiskDecision($score < 80, min(100, $score), $reasons);
    }
}
