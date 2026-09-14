<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The analysis layer: the things people actually open a money app to find out.
 */
final class InsightService
{
    public function __construct(private readonly BudgetService $budgets = new BudgetService) {}

    /** @return Collection<int, array{category:string, colour:string, total:Money, share:float}> */
    public function topCategories(User $user, CarbonImmutable $from, CarbonImmutable $to, int $limit = 6): Collection
    {
        $rows = Transaction::query()
            ->where('transactions.user_id', $user->id)
            ->where('type', TransactionType::Expense)
            ->whereBetween('booked_on', [$from->toDateString(), $to->toDateString()])
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->selectRaw('categories.name, categories.colour, SUM(COALESCE(base_amount_minor, amount_minor)) as total')
            ->groupBy('categories.name', 'categories.colour')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $grand = max(1, (int) $rows->sum('total'));

        return $rows->map(fn ($row): array => [
            'category' => $row->name ?? 'Uncategorised',
            'colour' => $row->colour ?? '#9AA4B2',
            'total' => new Money((int) $row->total, $user->base_currency),
            'share' => round((int) $row->total / $grand, 4),
        ]);
    }

    /**
     * Month against the same point in the previous month, not against the whole of it.
     * Comparing day 9 to a finished month always reads as "you are doing great", which is
     * useless advice on the 9th.
     */
    public function pacedAgainstLastMonth(User $user): array
    {
        $now = CarbonImmutable::now($user->timezone);
        [$start, $end] = $this->budgets->periodFor($user, $now);
        [$prevStart, $prevEnd] = $this->budgets->periodFor($user, $start->subDay());

        $dayOfPeriod = $start->diffInDays($now);

        $current = $this->totalSpend($user, $start, $now);
        $previous = $this->totalSpend($user, $prevStart, min($prevStart->addDays($dayOfPeriod), $prevEnd));

        $change = $previous->isZero()
            ? null
            : round(($current->minor - $previous->minor) / abs($previous->minor), 4);

        return [
            'current' => $current,
            'previous' => $previous,
            'change' => $change,
            'period_start' => $start,
            'period_end' => $end,
        ];
    }

    public function totalSpend(User $user, CarbonImmutable $from, CarbonImmutable $to): Money
    {
        $minor = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Expense)
            ->whereBetween('booked_on', [$from->toDateString(), $to->toDateString()])
            ->sum(Transaction::raw('COALESCE(base_amount_minor, amount_minor)'));

        return new Money((int) $minor, $user->base_currency);
    }

    /**
     * Find subscriptions the user never told us about.
     *
     * A merchant charging a similar amount at a steady interval three or more times is
     * almost certainly a recurring bill. Surfacing these is how people discover the gym
     * membership they stopped using in March.
     *
     * @return Collection<int, array{merchant:string, amount:Money, cadence_days:int, occurrences:int, annualised:Money}>
     */
    public function detectSubscriptions(User $user, int $lookbackMonths = 6): Collection
    {
        $since = CarbonImmutable::now($user->timezone)->subMonths($lookbackMonths);

        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Expense)
            ->whereNotNull('normalised_merchant')
            ->where('booked_on', '>=', $since->toDateString())
            ->orderBy('booked_on')
            ->get(['normalised_merchant', 'merchant', 'amount_minor', 'currency', 'booked_on'])
            ->groupBy('normalised_merchant')
            ->filter(fn (Collection $group): bool => $group->count() >= 3)
            ->map(function (Collection $group): ?array {
                $amounts = $group->pluck('amount_minor')->map(fn ($v): int => (int) $v);
                $median = $this->median($amounts->all());

                // Allow a 15% wobble: utility bills and usage-based charges are never identical.
                $consistent = $amounts->every(fn (int $v): bool => abs($v - $median) <= max(500, $median * 0.15));

                if (! $consistent) {
                    return null;
                }

                $gaps = $group->values()
                    ->sliding(2)
                    ->map(fn (Collection $pair): int => (int) $pair->first()->booked_on->diffInDays($pair->last()->booked_on))
                    ->filter(fn (int $d): bool => $d > 0);

                if ($gaps->isEmpty()) {
                    return null;
                }

                $cadence = (int) round($gaps->median());
                $spread = $gaps->max() - $gaps->min();

                // Irregular gaps mean it is a habit, not a subscription. A weekly coffee run
                // is not something you can cancel.
                if ($cadence < 6 || $spread > max(5, $cadence * 0.35)) {
                    return null;
                }

                $first = $group->first();
                $amount = new Money($median, $first->currency);

                return [
                    'merchant' => $first->merchant ?? $first->normalised_merchant,
                    'amount' => $amount,
                    'cadence_days' => $cadence,
                    'occurrences' => $group->count(),
                    'annualised' => $amount->times(365 / $cadence),
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row): int => $row['annualised']->minor)
            ->values();
    }

    /**
     * Money you fronted for other people and have not been paid back for, across every
     * group. The number people forget about.
     */
    public function outstandingFromOthers(User $user, GroupLedgerService $ledger): Money
    {
        $total = Money::zero($user->base_currency);

        foreach ($user->groups()->with(['members', 'transactions.splits', 'settlements'])->get() as $group) {
            $member = $group->members->firstWhere('user_id', $user->id);

            if ($member === null) {
                continue;
            }

            $position = $ledger->positionFor($group, $member);

            if ($position->isPositive() && $position->currency === $total->currency) {
                $total = $total->plus($position);
            }
        }

        return $total;
    }

    /** Strip order numbers, terminal ids and city suffixes so "SWIGGY*4471 BLR" groups with "Swiggy". */
    public static function normaliseMerchant(?string $merchant): ?string
    {
        if (blank($merchant)) {
            return null;
        }

        $value = Str::lower($merchant);
        $value = preg_replace('/\b(upi|neft|imps|pos|ach|txn|ref|nach)\b/i', ' ', $value);
        $value = preg_replace('/[0-9]{3,}/', ' ', $value);
        $value = preg_replace('/[^a-z ]/', ' ', $value);

        return Str::of($value)->squish()->limit(60, '')->trim()->value() ?: null;
    }

    /** @param list<int> $values */
    private function median(array $values): int
    {
        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$mid]
            : intdiv($values[$mid - 1] + $values[$mid], 2);
    }
}
