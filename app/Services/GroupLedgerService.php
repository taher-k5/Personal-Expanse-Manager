<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettlementStatus;
use App\Models\ExpenseGroup;
use App\Models\GroupMember;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Works out where everyone in a group stands.
 *
 * The rule is one line long: your balance is what you paid out, minus your share of
 * everything, adjusted for settlements you have already made or received. Positive means
 * the group owes you. The balances across a group always sum to zero — if they do not,
 * something upstream is wrong and the ledger will say so rather than quietly round.
 */
final class GroupLedgerService
{
    public function __construct(private readonly DebtSimplifier $simplifier = new DebtSimplifier) {}

    /** @return Collection<int, Money> member id => net position */
    public function balances(ExpenseGroup $group): Collection
    {
        $currency = $group->currency;

        $balances = $group->members
            ->mapWithKeys(fn (GroupMember $m): array => [$m->id => Money::zero($currency)]);

        $group->loadMissing(['transactions.splits', 'settlements']);

        foreach ($group->transactions as $transaction) {
            if ($transaction->paid_by_member_id !== null && $balances->has($transaction->paid_by_member_id)) {
                $balances[$transaction->paid_by_member_id] = $balances[$transaction->paid_by_member_id]
                    ->plus($transaction->groupAmount());
            }

            foreach ($transaction->splits as $split) {
                if (! $balances->has($split->group_member_id)) {
                    continue;
                }

                $balances[$split->group_member_id] = $balances[$split->group_member_id]
                    ->minus($split->amount);
            }
        }

        foreach ($group->settlements as $settlement) {
            if (! $settlement->status->countsTowardsBalance()) {
                continue;
            }

            if ($balances->has($settlement->from_member_id)) {
                $balances[$settlement->from_member_id] = $balances[$settlement->from_member_id]
                    ->plus($settlement->amount);
            }

            if ($balances->has($settlement->to_member_id)) {
                $balances[$settlement->to_member_id] = $balances[$settlement->to_member_id]
                    ->minus($settlement->amount);
            }
        }

        return $balances;
    }

    /**
     * The list of payments that would clear the group.
     *
     * @return list<array{from:GroupMember, to:GroupMember, amount:Money}>
     */
    public function settlementPlan(ExpenseGroup $group): array
    {
        $balances = $this->balances($group);

        $transfers = $group->simplify_debts
            ? DebtSimplifier::fromBalances($balances->all())
            : DebtSimplifier::netPairwise($this->pairwiseEdges($group));

        $members = $group->members->keyBy('id');

        return array_values(array_map(fn (array $t): array => [
            'from' => $members[$t['from']],
            'to' => $members[$t['to']],
            'amount' => $t['amount'],
        ], $transfers));
    }

    /**
     * Raw who-owes-whom edges, before any netting: for each expense, everyone in the split
     * owes their share to whoever paid.
     *
     * @return list<array{from:int, to:int, amount:Money}>
     */
    public function pairwiseEdges(ExpenseGroup $group): array
    {
        $edges = [];

        $group->loadMissing('transactions.splits');

        foreach ($group->transactions as $transaction) {
            $payer = $transaction->paid_by_member_id;

            if ($payer === null) {
                continue;
            }

            foreach ($transaction->splits as $split) {
                if ($split->group_member_id === $payer || $split->amount->isZero()) {
                    continue;
                }

                $edges[] = [
                    'from' => $split->group_member_id,
                    'to' => $payer,
                    'amount' => $split->amount,
                ];
            }
        }

        foreach ($group->settlements->where('status', SettlementStatus::Confirmed) as $settlement) {
            $edges[] = [
                'from' => $settlement->to_member_id,
                'to' => $settlement->from_member_id,
                'amount' => $settlement->amount,
            ];
        }

        return $edges;
    }

    /** What a single person should see at the top of the group screen. */
    public function positionFor(ExpenseGroup $group, GroupMember $member): Money
    {
        return $this->balances($group)->get($member->id, Money::zero($group->currency));
    }

    /**
     * A per-person summary for the group header: total paid, total share, net.
     *
     * @return Collection<int, array{member:GroupMember, paid:Money, share:Money, net:Money}>
     */
    public function summary(ExpenseGroup $group): Collection
    {
        $currency = $group->currency;
        $balances = $this->balances($group);

        $group->loadMissing('transactions.splits');

        return $group->members->map(function (GroupMember $member) use ($group, $balances, $currency): array {
            $paid = Money::sum(
                $group->transactions
                    ->where('paid_by_member_id', $member->id)
                    ->map(fn ($t) => $t->groupAmount()),
                $currency,
            );

            $share = Money::sum(
                $group->transactions
                    ->flatMap->splits
                    ->where('group_member_id', $member->id)
                    ->map(fn ($s) => $s->amount),
                $currency,
            );

            return [
                'member' => $member,
                'paid' => $paid,
                'share' => $share,
                'net' => $balances->get($member->id, Money::zero($currency)),
            ];
        })->keyBy(fn (array $row): int => $row['member']->id);
    }

    /** Guard for tests and for the group screen: balances must cancel out. */
    public function isBalanced(ExpenseGroup $group): bool
    {
        return Money::sum($this->balances($group)->values(), $group->currency)->isZero();
    }
}
