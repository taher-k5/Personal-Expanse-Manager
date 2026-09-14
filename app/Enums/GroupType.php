<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum GroupType: string implements HasIcon, HasLabel
{
    case Trip = 'trip';
    case Event = 'event';
    case Household = 'household';
    case Project = 'project';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Trip => 'Trip',
            self::Event => 'Event',
            self::Household => 'Shared home',
            self::Project => 'Project',
            self::Other => 'Something else',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Trip => 'heroicon-o-map',
            self::Event => 'heroicon-o-sparkles',
            self::Household => 'heroicon-o-home',
            self::Project => 'heroicon-o-briefcase',
            self::Other => 'heroicon-o-user-group',
        };
    }

    /** Trips and events end; a shared home does not. */
    public function isTimeBound(): bool
    {
        return in_array($this, [self::Trip, self::Event], true);
    }
}
