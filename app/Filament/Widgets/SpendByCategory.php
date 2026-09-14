<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\BudgetService;
use App\Services\InsightService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class SpendByCategory extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): ?string
    {
        return 'Where it went';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $user = auth()->user();
        [$start, $end] = app(BudgetService::class)->periodFor($user, CarbonImmutable::now($user->timezone));

        $rows = app(InsightService::class)->topCategories($user, $start, $end);

        return [
            'datasets' => [[
                'data' => $rows->map(fn (array $r): float => $r['total']->toFloat())->all(),
                'backgroundColor' => $rows->pluck('colour')->all(),
                'borderWidth' => 0,
            ]],
            'labels' => $rows->pluck('category')->all(),
        ];
    }
}
