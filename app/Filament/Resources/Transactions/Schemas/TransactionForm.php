<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\SplitMethod;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\ExpenseGroup;
use App\Models\GroupMember;
use App\Services\AutoCategoriser;
use App\Support\Money;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('What did you spend?')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->options(TransactionType::class)
                            ->default(TransactionType::Expense)
                            ->required()
                            ->live(),

                        // Currency sits before amount on purpose: the money cast reads the
                        // currency attribute when it converts the entered value to minor units.
                        Select::make('currency')
                            ->options(fn (): array => array_combine(
                                $codes = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'THB', 'JPY'],
                                $codes,
                            ))
                            ->default(fn () => auth()->user()->base_currency)
                            ->required()
                            ->live(),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->prefix(fn (Get $get): string => $get('currency') ?? 'INR')
                            ->formatStateUsing(fn ($state) => $state instanceof Money ? $state->toMajor() : $state)
                            ->columnSpanFull(),

                        DatePicker::make('booked_on')
                            ->label('Date')
                            ->default(today())
                            ->maxDate(today()->addYear())
                            ->required(),

                        TextInput::make('merchant')
                            ->label('Who was it paid to?')
                            ->datalist(fn (): array => auth()->user()
                                ->transactions()
                                ->whereNotNull('merchant')
                                ->distinct()
                                ->limit(50)
                                ->pluck('merchant')
                                ->all())
                            ->live(onBlur: true)
                            // Suggest a category from past behaviour, but never overwrite a choice.
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (filled($state) && blank($get('category_id'))) {
                                    $set('category_id', app(AutoCategoriser::class)->suggest(auth()->user(), $state));
                                }
                            }),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name', fn ($query) => $query->where('user_id', auth()->id()))
                            ->getOptionLabelFromRecordUsing(fn (Category $record): string => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->required(),
                                Select::make('kind')
                                    ->options(['expense' => 'Expense', 'income' => 'Income'])
                                    ->default('expense'),
                            ])
                            ->createOptionUsing(fn (array $data): int => auth()->user()
                                ->categories()
                                ->create($data)->id),

                        Select::make('account_id')
                            ->label('Paid from')
                            ->relationship('account', 'name', fn ($query) => $query->where('user_id', auth()->id())->where('is_archived', false))
                            ->searchable()
                            ->preload(),

                        Textarea::make('note')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Share it')
                    ->description('Attach this to a trip or event and decide who owes what.')
                    ->collapsed(fn (Get $get): bool => blank($get('expense_group_id')))
                    ->columns(2)
                    ->schema([
                        Select::make('expense_group_id')
                            ->label('Trip or event')
                            ->options(fn (): array => ExpenseGroup::query()
                                ->whereIn('id', auth()->user()->memberships()->pluck('expense_group_id'))
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('splits', [])),

                        Select::make('paid_by_member_id')
                            ->label('Who paid')
                            ->options(fn (Get $get): array => self::memberOptions($get('expense_group_id')))
                            ->visible(fn (Get $get): bool => filled($get('expense_group_id')))
                            ->default(fn (Get $get) => self::currentUserMemberId($get('expense_group_id')))
                            ->required(fn (Get $get): bool => filled($get('expense_group_id'))),

                        Select::make('split_method')
                            ->label('How to divide it')
                            ->options(SplitMethod::class)
                            ->default(SplitMethod::Equal)
                            ->helperText(fn ($state): string => ($state instanceof SplitMethod ? $state : SplitMethod::tryFrom((string) $state))?->hint() ?? '')
                            ->visible(fn (Get $get): bool => filled($get('expense_group_id')))
                            ->live()
                            ->columnSpanFull(),

                        Repeater::make('splits')
                            ->relationship()
                            ->label('Who was in on it')
                            ->visible(fn (Get $get): bool => filled($get('expense_group_id')) && $get('split_method') !== SplitMethod::None->value)
                            ->columns(2)
                            ->schema([
                                Select::make('group_member_id')
                                    ->label('Person')
                                    ->options(fn (Get $get): array => self::memberOptions($get('../../expense_group_id')))
                                    ->required()
                                    ->distinct(),

                                TextInput::make('split_input')
                                    ->label(fn (Get $get): string => match ($get('../../split_method')) {
                                        SplitMethod::Shares->value => 'Shares',
                                        SplitMethod::Percentage->value => 'Percent',
                                        SplitMethod::Exact->value => 'Exact amount',
                                        SplitMethod::Adjustment->value => 'Extra just for them',
                                        default => 'Share',
                                    })
                                    ->numeric()
                                    ->default(1)
                                    ->visible(fn (Get $get): bool => $get('../../split_method') !== SplitMethod::Equal->value),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Add someone')
                            ->columnSpanFull(),
                    ]),

                Section::make('Extras')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        FileUpload::make('receipt_path')
                            ->label('Receipt')
                            ->image()
                            ->imageEditor()
                            ->directory('receipts')
                            ->columnSpanFull(),

                        Toggle::make('is_reimbursable')
                            ->label('Someone owes me this back')
                            ->helperText('Work expenses and the like. Kept out of your own spending totals.'),

                        Toggle::make('exclude_from_budget')
                            ->label('Leave out of budgets')
                            ->helperText('For one-off spending you do not want skewing the month.'),
                    ]),
            ]);
    }

    /** @return array<int, string> */
    private static function memberOptions(int|string|null $groupId): array
    {
        if (blank($groupId)) {
            return [];
        }

        return GroupMember::query()
            ->where('expense_group_id', $groupId)
            ->pluck('display_name', 'id')
            ->all();
    }

    private static function currentUserMemberId(int|string|null $groupId): ?int
    {
        if (blank($groupId)) {
            return null;
        }

        return GroupMember::query()
            ->where('expense_group_id', $groupId)
            ->where('user_id', auth()->id())
            ->value('id');
    }
}
