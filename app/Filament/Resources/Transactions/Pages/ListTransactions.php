<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add expense')];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Everything'),
            'expenses' => Tab::make('Money out')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'expense')),
            'income' => Tab::make('Money in')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'income')),
            'shared' => Tab::make('Shared')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('expense_group_id'))
                ->badge(fn (): int => Transaction::query()
                    ->where('user_id', auth()->id())
                    ->whereNotNull('expense_group_id')
                    ->count()),
        ];
    }
}
