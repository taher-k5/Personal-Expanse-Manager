<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\BudgetService;
use App\Services\GroupLedgerService;
use App\Services\InsightService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four numbers worth seeing before anything else.
 */
class MonthOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        $insights = app(InsightService::class);
        $budgets = app(BudgetService::class);

        $pace = $insights->pacedAgainstLastMonth($user);
        $owed = $insights->outstandingFromOthers($user, app(GroupLedgerService::class));

        $change = $pace['change'];
        $direction = match (true) {
            $change === null => null,
            $change > 0.02 => 'up',
            $change < -0.02 => 'down',
            default => 'flat',
        };

        return [
            Stat::make('Spent this month', $pace['current']->format())
                ->description(match ($direction) {
                    'up' => sprintf('%s%% more than this point last month', round($change * 100)),
                    'down' => sprintf('%s%% less than this point last month', abs(round($change * 100))),
                    'flat' => 'About the same as last month',
                    default => 'No comparison yet',
                })
                ->descriptionIcon(match ($direction) {
                    'up' => 'heroicon-m-arrow-trending-up',
                    'down' => 'heroicon-m-arrow-trending-down',
                    default => 'heroicon-m-minus-small',
                })
                ->color(match ($direction) {
                    'up' => 'danger',
                    'down' => 'success',
                    default => 'gray',
                }),

            Stat::make('Safe to spend today', $budgets->safeToSpendToday($user)->format())
                ->description('What is left, spread evenly over the days that remain')
                ->color('primary'),

            Stat::make('Owed to you', $owed->format())
                ->description('Money you fronted on trips and events')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($owed->isPositive() ? 'warning' : 'gray'),

            Stat::make('Budgets off pace', (string) $budgets->status($user)->where('on_track', false)->count())
                ->description('Heading for an overspend at the current rate')
                ->color('warning'),
        ];
    }
}
