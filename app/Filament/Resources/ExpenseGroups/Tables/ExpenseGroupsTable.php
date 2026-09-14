<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseGroups\Tables;

use App\Enums\GroupType;
use App\Models\ExpenseGroup;
use App\Services\GroupLedgerService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpenseGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (ExpenseGroup $record): ?string => $record->destination),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('members_count')
                    ->counts('members')
                    ->label('People'),

                TextColumn::make('total')
                    ->label('Total spent')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->state(fn (ExpenseGroup $record): string => $record->total()->format()),

                // The only number that matters on this screen: are you up or down.
                TextColumn::make('position')
                    ->label('You')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->state(function (ExpenseGroup $record): string {
                        $member = $record->members->firstWhere('user_id', auth()->id());

                        if ($member === null) {
                            return '—';
                        }

                        $balance = app(GroupLedgerService::class)->positionFor($record, $member);

                        return match (true) {
                            $balance->isZero() => 'All square',
                            $balance->isPositive() => 'owed '.$balance->format(),
                            default => 'owe '.$balance->absolute()->format(),
                        };
                    })
                    ->color(function (ExpenseGroup $record): string {
                        $member = $record->members->firstWhere('user_id', auth()->id());
                        $balance = $member ? app(GroupLedgerService::class)->positionFor($record, $member) : Money::zero();

                        return match (true) {
                            $balance->isZero() => 'gray',
                            $balance->isPositive() => 'success',
                            default => 'danger',
                        };
                    }),
            ])
            ->filters([
                SelectFilter::make('type')->options(GroupType::class),
            ])
            ->recordActions([
                Action::make('ledger')
                    ->label('Open ledger')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ExpenseGroup $record): string => route('groups.show', $record)),

                EditAction::make(),
            ])
            ->emptyStateHeading('No trips or events yet')
            ->emptyStateDescription('Create one, add the people involved, and start logging what gets paid for.');
    }
}
