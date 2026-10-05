<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Support\Money;
use Safaricom\Mpesa\Support\Ids;

final class BillPaymentService
{
    public function __construct(
        private readonly BillerCatalog $catalog,
        private readonly PaymentOrchestrator $orchestrator,
    ) {}

    public function pay(string $billerId, string $payer, string $accountReference, Money $amount, string $idempotencyKey, array $metadata = []): PaymentResult
    {
        $biller = $this->catalog->get($billerId);
        $biller->validate($accountReference, $amount);
        $request = new PaymentRequest(
            amount: $amount,
            method: PaymentMethod::BILL,
            payer: $payer,
            payee: $biller->payee,
            reference: $accountReference,
            description: 'Bill payment to ' . $biller->name,
            metadata: array_merge($metadata, ['biller_id' => $biller->id]),
            id: Ids::payment('bill'),
        );
        return $this->orchestrator->collect($request, $idempotencyKey);
    }
}
