<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets;

use App\Models\Budget;
use App\Services\BudgetService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category_id')
                ->label('Category')
                ->relationship('category', 'name', fn (Builder $query) => $query->where('user_id', auth()->id()))
                ->searchable()
                ->preload()
                ->placeholder('Everything')
                ->helperText('Leave blank for one budget covering all your spending.'),

            Select::make('currency')
                ->options(['INR' => 'INR', 'USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP'])
                ->default(fn () => auth()->user()->base_currency)
                ->required(),

            TextInput::make('amount')
                ->numeric()
                ->required()
                ->formatStateUsing(fn ($state) => $state instanceof Money ? $state->toMajor() : $state),

            Select::make('period')
                ->options(['weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'])
                ->default('monthly')
                ->required(),

            DatePicker::make('starts_on')->default(today()->startOfMonth())->required(),

            TextInput::make('alert_threshold')
                ->label('Warn me at')
                ->numeric()
                ->suffix('of the budget')
                ->default(0.8)
                ->step(0.05)
                ->minValue(0.1)
                ->maxValue(1),

            Toggle::make('rollover')
                ->label('Carry unspent money forward')
                ->helperText('An underspent month makes next month roomier. Overspending carries forward too.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $status = app(BudgetService::class)->status(auth()->user())->keyBy('budget.id');

        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Budget')
                    ->state(fn (Budget $record): string => $record->label()),

                TextColumn::make('amount')
                    ->label('Limit')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->state(fn (Budget $record): string => $record->amount->format()),

                TextColumn::make('spent')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->state(fn (Budget $record): string => $status[$record->id]['spent']->format() ?? '—'),

                TextColumn::make('remaining')
                    ->label('Left')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->state(fn (Budget $record): string => $status[$record->id]['remaining']->format() ?? '—')
                    ->color(fn (Budget $record): string => ($status[$record->id]['remaining']->isNegative() ?? false) ? 'danger' : 'success'),

                // A budget you are under but spending too fast is still a problem. This
                // column is the early warning.
                TextColumn::make('pace')
                    ->label('Heading for')
                    ->alignEnd()
                    ->fontFamily('mono')
                    ->state(fn (Budget $record): string => $status[$record->id]['projected']->format() ?? '—')
                    ->color(fn (Budget $record): string => ($status[$record->id]['on_track'] ?? true) ? 'gray' : 'warning')
                    ->tooltip('Where this month lands if you keep spending at the current rate.'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function getPages(): array
    {
        return ['index' => \App\Filament\Resources\Budgets\Pages\ManageBudgets::route('/')];
    }
}
