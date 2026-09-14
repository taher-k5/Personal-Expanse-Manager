<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Budget tracking with the two things that make a budget useful rather than decorative:
 * a projection of where the month is heading, and a daily number you can actually act on.
 */
final class BudgetService
{
    /**
     * @return Collection<int, array{
     *     budget: Budget, spent: Money, limit: Money, remaining: Money,
     *     used: float, projected: Money, on_track: bool, daily_allowance: Money
     * }>
     */
    public function status(User $user, ?CarbonImmutable $month = null): Collection
    {
        $month ??= CarbonImmutable::now($user->timezone);

        [$start, $end] = $this->periodFor($user, $month);
        $today = CarbonImmutable::now($user->timezone)->startOfDay();

        $elapsed = max(1, $start->diffInDays(min($today, $end)) + 1);
        $length = max(1, $start->diffInDays($end) + 1);
        $daysLeft = max(1, $end->diffInDays(max($today, $start)));

        $spendByCategory = $this->spendByCategory($user, $start, $end);

        return $user->budgets()
            ->where('is_active', true)
            ->with('category')
            ->get()
            ->map(function (Budget $budget) use ($spendByCategory, $elapsed, $length, $daysLeft, $user): array {
                $spent = $budget->category_id === null
                    ? Money::sum($spendByCategory->values(), $user->base_currency)
                    : $spendByCategory->get($budget->category_id, Money::zero($user->base_currency));

                // Rollover budgets carry unspent money forward, so the usable limit this
                // period is the budget plus whatever survived the last one.
                $limit = $budget->rollover
                    ? $budget->amount->plus(new Money($budget->rollover_balance_minor, $budget->currency))
                    : $budget->amount;

                $remaining = $limit->minus($spent);
                $projected = $spent->times($length / $elapsed);

                return [
                    'budget' => $budget,
                    'spent' => $spent,
                    'limit' => $limit,
                    'remaining' => $remaining,
                    'used' => $limit->isZero() ? 0.0 : round($spent->minor / $limit->minor, 4),
                    'projected' => $projected,
                    'on_track' => $projected->compareTo($limit) <= 0,
                    'daily_allowance' => $remaining->isPositive()
                        ? new Money(intdiv($remaining->minor, $daysLeft), $remaining->currency)
                        : Money::zero($remaining->currency),
                ];
            });
    }

    /**
     * The single number worth putting on the home screen: what is left to spend today
     * without blowing the month, across every budget that is still in credit.
     */
    public function safeToSpendToday(User $user): Money
    {
        return Money::sum(
            $this->status($user)
                ->where('budget.category_id', null)
                ->pluck('daily_allowance')
                ->whenEmpty(fn () => $this->status($user)->pluck('daily_allowance')),
            $user->base_currency,
        );
    }

    /** @return Collection<int, Money> category id => spend */
    public function spendByCategory(User $user, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Expense)
            ->where('exclude_from_budget', false)
            ->whereBetween('booked_on', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('category_id, SUM(COALESCE(base_amount_minor, amount_minor)) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($total): Money => new Money((int) $total, $user->base_currency));
    }

    /**
     * Close out a period: carry the unspent balance forward on rollover budgets.
     * Overspending carries forward too, as a negative — that is the point of envelopes.
     */
    public function rollPeriod(Budget $budget, Money $spent): void
    {
        if (! $budget->rollover) {
            return;
        }

        $limit = $budget->amount->plus(new Money($budget->rollover_balance_minor, $budget->currency));

        $budget->update([
            'rollover_balance_minor' => $limit->minus($spent)->minor,
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function periodFor(User $user, CarbonImmutable $reference): array
    {
        $startDay = max(1, min(28, $user->month_starts_on ?? 1));

        $start = $reference->day >= $startDay
            ? $reference->setDay($startDay)
            : $reference->subMonthNoOverflow()->setDay($startDay);

        return [$start->startOfDay(), $start->addMonthNoOverflow()->subDay()->endOfDay()];
    }
}
