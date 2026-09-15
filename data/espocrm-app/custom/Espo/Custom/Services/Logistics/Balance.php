<?php
namespace Espo\Custom\Services\Logistics;

/** Integer minor-unit ledger reduction. Does not read or write the database. */
final class Balance
{
    public static function calculate(array $shipments, array $entries): array
    {
        $funds = 0; $openingDebt = 0; $allocated = []; $returns = [];
        foreach ($entries as $e) {
            $amount = Money::minor($e['amountVnd']);
            $id = $e['shipmentId'] ?? '';
            switch ($e['kind']) {
                case 'receipt': case 'openingCredit': $funds += $amount; break;
                case 'openingDebit': $openingDebt += $amount; break;
                case 'allocation': $allocated[$id] = ($allocated[$id] ?? 0) + $amount; break;
                case 'release': $allocated[$id] = ($allocated[$id] ?? 0) - $amount; break;
                case 'returnCredit': $returns[$id] = ($returns[$id] ?? 0) + $amount; break;
            }
        }
        $due = max(0, $openingDebt - ($allocated[''] ?? 0));
        $rows = [];
        foreach ($shipments as $s) {
            $id = $s['id'];
            $charge = $s['workflowStatus'] === 'confirmed' ? Money::minor($s['postedAmountVnd']) : 0;
            $net = max(0, $charge - ($returns[$id] ?? 0));
            $paid = $allocated[$id] ?? 0;
            $remaining = max(0, $net - $paid);
            $rows[$id] = ['charge' => $charge, 'net' => $net, 'paid' => $paid,
                'returned' => $returns[$id] ?? 0, 'due' => $remaining];
            $due += $remaining;
        }
        $credit = $funds - array_sum($allocated);
        return ['shipments' => $rows, 'openingDue' => max(0, $openingDebt - ($allocated[''] ?? 0)),
            'credit' => $credit, 'due' => $due, 'balance' => $due - $credit];
    }
}
