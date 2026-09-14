<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasColor, HasIcon, HasLabel
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Expense => 'Money out',
            self::Income => 'Money in',
            self::Transfer => 'Moved between accounts',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Expense => 'danger',
            self::Income => 'success',
            self::Transfer => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Expense => 'heroicon-o-arrow-up-right',
            self::Income => 'heroicon-o-arrow-down-left',
            self::Transfer => 'heroicon-o-arrows-right-left',
        };
    }

    /** Expenses reduce an account balance; income raises it. */
    public function signum(): int
    {
        return $this === self::Income ? 1 : -1;
    }
}
