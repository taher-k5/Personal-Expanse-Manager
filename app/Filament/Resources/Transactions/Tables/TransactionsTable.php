<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\AutoCategoriser;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('booked_on', 'desc')
            ->groups(['booked_on'])
            ->defaultGroup('booked_on')
            ->columns([
                TextColumn::make('booked_on')
                    ->label('Date')
                    ->date('j M')
                    ->sortable(),

                TextColumn::make('merchant')
                    ->label('Paid to')
                    ->description(fn (Transaction $record): ?string => $record->note)
                    ->searchable(['merchant', 'note'])
                    ->placeholder('No description'),

                TextColumn::make('category.name')
                    ->badge()
                    ->color(fn (Transaction $record): string => $record->category?->colour ?? 'gray')
                    ->placeholder('Uncategorised')
                    ->sortable(),

                TextColumn::make('group.name')
                    ->label('Shared with')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('account.name')
                    ->label('From')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_reimbursable')
                    ->label('Owed back')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                // The amount is the thing people scan for, so it is aligned right in
                // tabular figures and coloured by direction rather than by category.
                TextColumn::make('amount')
                    ->alignEnd()
                    ->weight('semibold')
                    ->fontFamily('mono')
                    ->color(fn (Transaction $record): string => $record->type->getColor())
                    ->formatStateUsing(fn ($state, Transaction $record): string => sprintf(
                        '%s%s',
                        $record->type === TransactionType::Income ? '+' : '−',
                        $state->absolute()->format(),
                    ))
                    ->summarize([])
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('amount_minor', $direction)),
            ])
            ->filters([
                SelectFilter::make('type')->options(TransactionType::class),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('expense_group_id')
                    ->label('Trip or event')
                    ->relationship('group', 'name')
                    ->preload(),

                Filter::make('uncategorised')
                    ->label('Needs a category')
                    ->query(fn (Builder $query): Builder => $query->whereNull('category_id'))
                    ->toggle(),

                Filter::make('this_month')
                    ->label('This month')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('booked_on', [
                        now()->startOfMonth(), now()->endOfMonth(),
                    ]))
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Bulk re-filing is the fastest way to clean up an import, and every
                    // correction teaches the categoriser something.
                    BulkAction::make('recategorise')
                        ->label('Move to category')
                        ->icon('heroicon-o-tag')
                        ->schema([
                            Select::make('category_id')
                                ->label('Category')
                                ->options(fn (): array => auth()->user()->categories()->pluck('name', 'id')->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $categoriser = app(AutoCategoriser::class);

                            $records->each(function (Transaction $record) use ($data, $categoriser): void {
                                $record->update(['category_id' => $data['category_id']]);
                                $categoriser->learnFrom($record);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No spending recorded yet')
            ->emptyStateDescription('Add your first expense and the charts will fill in from there.');
    }
}
