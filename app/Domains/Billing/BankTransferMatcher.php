<?php

namespace App\Domains\Billing;

use App\Models\BillingOrder;

class BankTransferMatcher
{
    public const CODE_PATTERN = '/SHUB[A-Z0-9]{10}/i';

    /**
     * @param  array<int, array<string, mixed>>  $transactions
     * @param  iterable<BillingOrder>  $pendingOrders
     * @return list<array{order: BillingOrder, txId: string, amount: int, description: string}>
     */
    public function match(array $transactions, iterable $pendingOrders): array
    {
        $byCode = [];
        foreach ($pendingOrders as $order) {
            $byCode[strtoupper($order->order_code)] = $order;
        }

        $matches = [];
        $usedTxn = [];

        foreach ($transactions as $tx) {
            if (($tx['creditDebitIndicator'] ?? '') !== 'CRDT') {
                continue;
            }

            $txId = isset($tx['id']) ? (string) $tx['id'] : '';
            if ($txId === '' || isset($usedTxn[$txId])) {
                continue;
            }

            $amount = (int) round((float) $tx['amount']);
            $description = trim(preg_replace('/\s+/u', ' ', (string) ($tx['description'] ?? '')) ?? '');

            if (! preg_match_all(self::CODE_PATTERN, $description, $found)) {
                continue;
            }

            foreach ($found[0] as $rawCode) {
                $code = strtoupper($rawCode);
                $order = $byCode[$code] ?? null;
                if (! $order || (int) $order->amount_vnd !== $amount) {
                    continue;
                }

                $matches[] = [
                    'order' => $order,
                    'txId' => $txId,
                    'amount' => $amount,
                    'description' => $description,
                ];
                $usedTxn[$txId] = true;
                unset($byCode[$code]);
                break;
            }
        }

        return $matches;
    }
}
