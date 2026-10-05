<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class BillerCatalog
{
    /** @var array<string, Biller> */
    private array $billers = [];
    public function register(Biller $biller): void { $this->billers[$biller->id] = $biller; }
    public function get(string $id): Biller { return $this->billers[$id] ?? throw PaymentException::validation('Unknown biller.'); }
    /** @return list<Biller> */
    public function all(): array { return array_values($this->billers); }
}
