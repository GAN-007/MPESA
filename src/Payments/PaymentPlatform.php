<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Ids;
use Safaricom\Mpesa\Support\Money;

/**
 * High-level composition root that turns the independent payment primitives into
 * complete user journeys: mobile-money checkout, alias transfers, payment links,
 * bills, rewards, ledger posting, settlement and reconciliation.
 */
final class PaymentPlatform
{
    public readonly ProviderRegistry $providers;
    public readonly PaymentRouter $router;
    public readonly PaymentOrchestrator $orchestrator;
    public readonly AliasDirectory $aliases;
    public readonly BillerCatalog $billers;
    public readonly BillPaymentService $billPayments;
    public readonly Ledger $ledger;
    public readonly RewardsEngine $rewards;
    public readonly AgentNetwork $agents;
    public readonly ReconciliationService $reconciliation;

    /** @var array<string, SettlementWallet> */
    private array $settlementWallets = [];

    /** @param list<PaymentProvider> $providers */
    public function __construct(
        array $providers,
        IdempotencyStore $idempotency,
        ?RiskEngine $risk = null,
        private readonly ?PaymentLinkService $paymentLinks = null,
    ) {
        $this->providers = new ProviderRegistry();
        foreach ($providers as $provider) {
            if (!$provider instanceof PaymentProvider) {
                throw PaymentException::configuration('PaymentPlatform providers must implement PaymentProvider.');
            }
            $this->providers->register($provider);
        }
        if ($this->providers->count() === 0) {
            throw PaymentException::configuration('PaymentPlatform requires at least one payment provider.');
        }

        $this->router = new PaymentRouter($this->providers);
        $this->orchestrator = new PaymentOrchestrator($this->router, $risk ?? new RiskEngine(), $idempotency);
        $this->aliases = new AliasDirectory();
        $this->billers = new BillerCatalog();
        $this->billPayments = new BillPaymentService($this->billers, $this->orchestrator);
        $this->ledger = new Ledger();
        $this->rewards = new RewardsEngine();
        $this->agents = new AgentNetwork();
        $this->reconciliation = new ReconciliationService();
    }

    public function preferProvider(string $provider, int $priority): void
    {
        $this->providers->get($provider);
        $this->router->prefer($provider, $priority);
    }

    public function collect(PaymentRequest $request, string $idempotencyKey): PaymentResult
    {
        return $this->orchestrator->collect($request, $idempotencyKey);
    }

    public function payAlias(
        string $aliasType,
        string $alias,
        string $payer,
        Money $amount,
        string $reference,
        string $description,
        string $idempotencyKey,
        PaymentMethod $method = PaymentMethod::MOBILE_MONEY,
        array $metadata = [],
    ): PaymentResult {
        $resolved = $this->aliases->resolve($aliasType, $alias);
        if ($resolved->currency !== $amount->currency) {
            throw PaymentException::validation('Payment alias currency does not match payment currency.');
        }
        return $this->collect(new PaymentRequest(
            amount: $amount,
            method: $method,
            payer: $payer,
            payee: $resolved->destination,
            reference: $reference,
            description: $description,
            metadata: array_merge($resolved->metadata, $metadata, [
                'alias' => $resolved->alias,
                'alias_type' => $resolved->type,
            ]),
            id: Ids::payment('alias'),
        ), $idempotencyKey);
    }

    public function payBill(
        string $billerId,
        string $payer,
        string $accountReference,
        Money $amount,
        string $idempotencyKey,
        array $metadata = [],
    ): PaymentResult {
        return $this->billPayments->pay($billerId, $payer, $accountReference, $amount, $idempotencyKey, $metadata);
    }

    public function createPaymentLink(
        string $merchant,
        Money $amount,
        string $reference,
        int $ttlSeconds = 3600,
        array $metadata = [],
        ?int $now = null,
    ): PaymentLink {
        return $this->requirePaymentLinks()->create($merchant, $amount, $reference, $ttlSeconds, $metadata, $now);
    }

    public function collectPaymentLink(
        string $token,
        string $payer,
        string $idempotencyKey,
        array $metadata = [],
        ?int $now = null,
    ): PaymentResult {
        $link = $this->requirePaymentLinks()->verify($token, $now);
        return $this->collect(new PaymentRequest(
            amount: $link->amount,
            method: PaymentMethod::PAYMENT_LINK,
            payer: $payer,
            payee: $link->merchant,
            reference: $link->reference,
            description: 'Payment link checkout ' . $link->reference,
            metadata: array_merge($link->metadata, $metadata, ['payment_link_expires_at' => $link->expiresAt]),
            id: Ids::payment('link'),
        ), $idempotencyKey);
    }

    public function settlementWallet(string $id, string $currency = 'KES', int $scale = 2): SettlementWallet
    {
        $key = $id . '|' . $currency . '|' . $scale;
        if (!isset($this->settlementWallets[$key])) {
            $this->settlementWallets[$key] = new SettlementWallet($id, $currency, $scale);
        }
        return $this->settlementWallets[$key];
    }

    public function recordSuccessfulPayment(
        string $journalId,
        string $sourceAccount,
        string $destinationAccount,
        Money $amount,
        string $reference,
        ?string $rewardCustomerId = null,
        int $rewardBasisPoints = 0,
        array $metadata = [],
    ): JournalTransaction {
        $transaction = $this->ledger->transfer($sourceAccount, $destinationAccount, $amount, $reference, $journalId, $metadata);
        if ($rewardCustomerId !== null && $rewardBasisPoints > 0) {
            $this->rewards->earn($rewardCustomerId, $amount->minor, $rewardBasisPoints);
        }
        return $transaction;
    }

    private function requirePaymentLinks(): PaymentLinkService
    {
        return $this->paymentLinks ?? throw PaymentException::configuration('PaymentLinkService was not configured for this PaymentPlatform.');
    }
}
