<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SplitMethod: string implements HasLabel
{
    case None = 'none';
    case Equal = 'equal';
    case Shares = 'shares';
    case Percentage = 'percentage';
    case Exact = 'exact';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Just mine',
            self::Equal => 'Split equally',
            self::Shares => 'Split by shares',
            self::Percentage => 'Split by percentage',
            self::Exact => 'Enter exact amounts',
            self::Adjustment => 'Equally, plus extras',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::None => 'Nobody owes you anything for this.',
            self::Equal => 'Everyone selected pays the same.',
            self::Shares => 'Give each person a weight. A couple sharing a room gets 2.',
            self::Percentage => 'Percentages must add up to 100.',
            self::Exact => 'The amounts must add up to the total.',
            self::Adjustment => 'Add what each person had on top, then split the rest equally.',
        };
    }

    public function needsInputPerMember(): bool
    {
        return $this !== self::Equal && $this !== self::None;
    }
}
