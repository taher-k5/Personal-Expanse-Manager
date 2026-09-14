<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SettlementStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Not paid yet',
            self::AwaitingConfirmation => 'Waiting for them to confirm',
            self::Confirmed => 'Settled',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::AwaitingConfirmation => 'warning',
            self::Confirmed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Only confirmed money actually moves the ledger. */
    public function countsTowardsBalance(): bool
    {
        return $this === self::Confirmed;
    }
}
