<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class ProviderRegistry
{
    /** @var array<string, PaymentProvider> */
    private array $providers = [];

    public function register(PaymentProvider $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    /** @return list<PaymentProvider> */
    public function compatible(PaymentRequest $request): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn (PaymentProvider $provider): bool => $provider->supports($request)
        ));
    }

    public function get(string $name): PaymentProvider
    {
        return $this->providers[$name]
            ?? throw PaymentException::configuration("Payment provider '{$name}' is not registered.");
    }

    public function count(): int
    {
        return count($this->providers);
    }
}
