<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseGroups\Schemas;

use App\Enums\GroupType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ExpenseGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->placeholder('Goa, March')
                            ->required()
                            ->columnSpanFull(),

                        Select::make('type')
                            ->options(GroupType::class)
                            ->default(GroupType::Trip)
                            ->live()
                            ->required(),

                        Select::make('currency')
                            ->options(fn (): array => array_combine(
                                $codes = ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'THB', 'JPY'],
                                $codes,
                            ))
                            ->default(fn () => auth()->user()->base_currency)
                            ->helperText('Everything in this group is tallied in one currency.')
                            ->required(),

                        DatePicker::make('starts_on')
                            ->visible(fn (Get $get): bool => GroupType::tryFrom((string) $get('type'))?->isTimeBound() ?? false),

                        DatePicker::make('ends_on')
                            ->afterOrEqual('starts_on')
                            ->visible(fn (Get $get): bool => GroupType::tryFrom((string) $get('type'))?->isTimeBound() ?? false),

                        TextInput::make('destination')
                            ->visible(fn (Get $get): bool => $get('type') === GroupType::Trip->value),

                        Textarea::make('description')->rows(2)->columnSpanFull(),
                    ]),

                Section::make('Who is in')
                    ->description('Add people by name. They do not need an account — you can send them a link later.')
                    ->schema([
                        Repeater::make('members')
                            ->relationship()
                            ->hiddenLabel()
                            ->columns(3)
                            ->schema([
                                TextInput::make('display_name')
                                    ->label('Name')
                                    ->required(),

                                TextInput::make('email')
                                    ->email()
                                    ->label('Email')
                                    ->helperText('Only needed to send them an invite.'),

                                TextInput::make('upi_vpa')
                                    ->label('UPI ID')
                                    ->placeholder('name@bank')
                                    ->helperText('Lets others pay them back in one tap.'),
                            ])
                            ->addActionLabel('Add a person')
                            ->defaultItems(1)
                            ->reorderable(false),
                    ]),

                Section::make('Settling up')
                    ->columns(2)
                    ->schema([
                        Toggle::make('simplify_debts')
                            ->label('Reduce the number of payments')
                            ->default(true)
                            ->helperText('Nets everything down so people pay whoever clears the most debt, even if they never shared an expense directly.'),

                        Toggle::make('share_enabled')
                            ->label('Share a read-only link')
                            ->helperText('Anyone with the link can see the ledger. Nobody can change it.'),
                    ]),
            ]);
    }
}
