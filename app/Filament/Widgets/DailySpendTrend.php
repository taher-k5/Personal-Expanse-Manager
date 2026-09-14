<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\BudgetService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Cumulative spend against a straight-line budget.
 *
 * A daily bar chart tells you nothing at a glance. The running total against the pace line
 * tells you immediately whether the month is drifting, and roughly when it started to.
 */
class DailySpendTrend extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 2;

    public function getHeading(): ?string
    {
        return 'This month, day by day';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        $budgets = app(BudgetService::class);
        [$start, $end] = $budgets->periodFor($user, CarbonImmutable::now($user->timezone));

        $daily = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::Expense)
            ->whereBetween('booked_on', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('booked_on, SUM(COALESCE(base_amount_minor, amount_minor)) as total')
            ->groupBy('booked_on')
            ->pluck('total', 'booked_on');

        $labels = [];
        $cumulative = [];
        $running = 0;

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $running += (int) ($daily[$day->toDateString()] ?? 0);
            $labels[] = $day->format('j');
            $cumulative[] = $day->isAfter(CarbonImmutable::now($user->timezone))
                ? null
                : round($running / 100, 2);
        }

        $limit = $budgets->status($user)->sum(fn (array $row): int => $row['limit']->minor) / 100;
        $days = max(1, count($labels));

        return [
            'datasets' => [
                [
                    'label' => 'Spent so far',
                    'data' => $cumulative,
                    'borderColor' => '#1F4FD8',
                    'backgroundColor' => 'rgba(31, 79, 216, 0.08)',
                    'fill' => true,
                    'tension' => 0.25,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Budget pace',
                    'data' => array_map(fn (int $i): float => round($limit * ($i + 1) / $days, 2), array_keys($labels)),
                    'borderColor' => '#9AA4B2',
                    'borderDash' => [4, 4],
                    'fill' => false,
                    'pointRadius' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
