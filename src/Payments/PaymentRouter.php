<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class PaymentRouter
{
    /** @var array<string, int> */
    private array $priority = [];

    public function __construct(private readonly ProviderRegistry $registry) {}

    public function prefer(string $provider, int $priority): void
    {
        $this->priority[$provider] = $priority;
    }

    public function route(PaymentRequest $request): PaymentProvider
    {
        $providers = $this->registry->compatible($request);
        if ($providers === []) {
            throw PaymentException::provider('No registered payment rail supports this request.');
        }

        usort($providers, function (PaymentProvider $a, PaymentProvider $b): int {
            return ($this->priority[$b->name()] ?? 0) <=> ($this->priority[$a->name()] ?? 0);
        });

        return $providers[0];
    }
}
