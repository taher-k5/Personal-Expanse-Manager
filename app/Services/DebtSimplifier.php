<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Money;

/**
 * Reduces a tangle of who-owes-whom into the fewest payments that clear it.
 *
 * If Aman owes Bilal 500 and Bilal owes Chetna 500, the simplified ledger is a single
 * payment from Aman to Chetna. For n people with a non-zero balance this always produces
 * at most n-1 transfers, which is the practical minimum for a greedy pass.
 *
 * Note the trade-off: simplification produces payments between people who never directly
 * shared an expense. Some groups find that confusing, which is why it is a per-group
 * setting rather than always-on behaviour.
 */
final class DebtSimplifier
{
    /**
     * @param  array<int, Money>  $balances  member id => net position (positive = owed money)
     * @return list<array{from:int, to:int, amount:Money}>
     */
    public static function fromBalances(array $balances): array
    {
        $creditors = [];
        $debtors = [];
        $currency = 'INR';

        foreach ($balances as $memberId => $balance) {
            $currency = $balance->currency;

            if ($balance->isPositive()) {
                $creditors[$memberId] = $balance->minor;
            } elseif ($balance->isNegative()) {
                $debtors[$memberId] = -$balance->minor;
            }
        }

        // Largest amounts first: settling the biggest pair each round is what keeps the
        // number of payments down, and it also matches how people actually settle up.
        arsort($creditors);
        arsort($debtors);

        $transfers = [];

        while ($creditors !== [] && $debtors !== []) {
            $creditorId = array_key_first($creditors);
            $debtorId = array_key_first($debtors);

            $amount = min($creditors[$creditorId], $debtors[$debtorId]);

            $transfers[] = [
                'from' => $debtorId,
                'to' => $creditorId,
                'amount' => new Money($amount, $currency),
            ];

            $creditors[$creditorId] -= $amount;
            $debtors[$debtorId] -= $amount;

            if ($creditors[$creditorId] === 0) {
                unset($creditors[$creditorId]);
            }

            if ($debtors[$debtorId] === 0) {
                unset($debtors[$debtorId]);
            }

            arsort($creditors);
            arsort($debtors);
        }

        return $transfers;
    }

    /**
     * Keep debts between the people who actually shared the expense.
     *
     * Used when a group turns simplification off. Each pair is netted against itself, so
     * two people who paid for each other across the week end up with one number, but
     * unrelated people are never connected.
     *
     * @param  list<array{from:int, to:int, amount:Money}>  $edges
     * @return list<array{from:int, to:int, amount:Money}>
     */
    public static function netPairwise(array $edges): array
    {
        $pairs = [];

        foreach ($edges as $edge) {
            [$low, $high] = $edge['from'] < $edge['to']
                ? [$edge['from'], $edge['to']]
                : [$edge['to'], $edge['from']];

            $key = "{$low}:{$high}";
            $direction = $edge['from'] === $low ? 1 : -1;

            $pairs[$key] ??= ['low' => $low, 'high' => $high, 'minor' => 0, 'currency' => $edge['amount']->currency];
            $pairs[$key]['minor'] += $direction * $edge['amount']->minor;
        }

        $transfers = [];

        foreach ($pairs as $pair) {
            if ($pair['minor'] === 0) {
                continue;
            }

            $transfers[] = $pair['minor'] > 0
                ? ['from' => $pair['low'], 'to' => $pair['high'], 'amount' => new Money($pair['minor'], $pair['currency'])]
                : ['from' => $pair['high'], 'to' => $pair['low'], 'amount' => new Money(-$pair['minor'], $pair['currency'])];
        }

        return $transfers;
    }
}
