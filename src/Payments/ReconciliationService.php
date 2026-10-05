<?php
declare(strict_types=1);

namespace Safaricom\Mpesa\Payments;

use Safaricom\Mpesa\Exception\PaymentException;

final class ReconciliationService
{
    public function reconcile(array $internal, array $provider): ReconciliationReport
    {
        $left = $this->index($internal, 'internal');
        $right = $this->index($provider, 'provider');
        $matched = [];
        $missingAtProvider = [];
        $missingInternally = [];
        $mismatches = [];

        foreach ($left as $reference => $item) {
            if (!isset($right[$reference])) { $missingAtProvider[] = $reference; continue; }
            $other = $right[$reference];
            $fields = [];
            foreach (['amountMinor', 'currency', 'status'] as $field) {
                if ($item->{$field} !== $other->{$field}) $fields[$field] = ['internal' => $item->{$field}, 'provider' => $other->{$field}];
            }
            if ($fields === []) $matched[] = $reference; else $mismatches[$reference] = $fields;
        }

        foreach ($right as $reference => $_item) if (!isset($left[$reference])) $missingInternally[] = $reference;

        sort($matched);
        sort($missingAtProvider);
        sort($missingInternally);
        ksort($mismatches);
        return new ReconciliationReport($matched, $missingAtProvider, $missingInternally, $mismatches);
    }

    private function index(array $items, string $side): array
    {
        $indexed = [];
        foreach ($items as $item) {
            if (!$item instanceof ReconciliationItem) throw PaymentException::validation("{$side} reconciliation collection contains a non-ReconciliationItem value.");
            if (isset($indexed[$item->providerReference])) throw PaymentException::validation("Duplicate {$side} provider reference: {$item->providerReference}");
            $indexed[$item->providerReference] = $item;
        }
        return $indexed;
    }
}
