<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;
use Safaricom\Mpesa\Support\Ids;
use Safaricom\Mpesa\Support\Money;

/**
 * Models the two-sided liquidity that makes African agent networks useful:
 * physical cash and electronic float. It deliberately does not move real funds;
 * a provider adapter performs the regulated transaction while this class enforces
 * liquidity invariants and records the agent-side state transition.
 */
final class AgentNetwork
{
    /** @var array<string, Agent> */
    private array $agents = [];
    /** @var array<string, AgentTransaction> */
    private array $transactions = [];

    public function register(Agent $agent): void
    {
        if (isset($this->agents[$agent->id])) {
            throw PaymentException::validation('Agent id already exists.');
        }
        $this->agents[$agent->id] = $agent;
    }

    public function get(string $agentId): Agent
    {
        return $this->agents[$agentId] ?? throw PaymentException::validation('Unknown agent.');
    }

    public function cashIn(string $agentId, string $customer, Money $amount, ?string $transactionId = null, array $metadata = []): AgentTransaction
    {
        $agent = $this->get($agentId);
        $this->assertMoney($agent, $amount);
        if ($agent->electronicFloatMinor < $amount->minor) {
            throw PaymentException::validation('Agent has insufficient electronic float for cash-in.');
        }
        $transaction = $this->newTransaction($transactionId, $agent, 'cash_in', $customer, $amount, $metadata);
        if (isset($this->transactions[$transaction->id])) {
            return $this->transactions[$transaction->id];
        }
        $agent->electronicFloatMinor -= $amount->minor;
        $agent->cashMinor += $amount->minor;
        return $this->transactions[$transaction->id] = $transaction;
    }

    public function cashOut(string $agentId, string $customer, Money $amount, ?string $transactionId = null, array $metadata = []): AgentTransaction
    {
        $agent = $this->get($agentId);
        $this->assertMoney($agent, $amount);
        if ($agent->cashMinor < $amount->minor) {
            throw PaymentException::validation('Agent has insufficient physical cash for cash-out.');
        }
        $transaction = $this->newTransaction($transactionId, $agent, 'cash_out', $customer, $amount, $metadata);
        if (isset($this->transactions[$transaction->id])) {
            return $this->transactions[$transaction->id];
        }
        $agent->cashMinor -= $amount->minor;
        $agent->electronicFloatMinor += $amount->minor;
        return $this->transactions[$transaction->id] = $transaction;
    }

    public function addLiquidity(string $agentId, Money $electronicFloat, Money $cash): void
    {
        $agent = $this->get($agentId);
        $this->assertMoney($agent, $electronicFloat);
        $this->assertMoney($agent, $cash);
        $agent->electronicFloatMinor += $electronicFloat->minor;
        $agent->cashMinor += $cash->minor;
    }

    public function transaction(string $id): ?AgentTransaction
    {
        return $this->transactions[$id] ?? null;
    }

    private function newTransaction(?string $id, Agent $agent, string $type, string $customer, Money $amount, array $metadata): AgentTransaction
    {
        $id ??= Ids::payment('agt');
        if ($customer === '' || $amount->minor <= 0) {
            throw PaymentException::validation('Agent transaction requires a customer and positive amount.');
        }
        if (isset($this->transactions[$id])) {
            $existing = $this->transactions[$id];
            if ($existing->agentId !== $agent->id || $existing->type !== $type || $existing->customer !== $customer || $existing->amount->minor !== $amount->minor || $existing->amount->currency !== $amount->currency) {
                throw PaymentException::validation('Agent transaction id was reused with different transaction details.');
            }
            return $existing;
        }
        return new AgentTransaction($id, $agent->id, $type, $customer, $amount, time(), $metadata);
    }

    private function assertMoney(Agent $agent, Money $amount): void
    {
        if ($amount->currency !== $agent->currency || $amount->scale !== 2) {
            throw PaymentException::validation('Agent transaction currency/scale does not match the agent position.');
        }
    }
}
