<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum RecurrenceFrequency: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Fortnightly = 'fortnightly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Every day',
            self::Weekly => 'Every week',
            self::Fortnightly => 'Every two weeks',
            self::Monthly => 'Every month',
            self::Quarterly => 'Every quarter',
            self::Yearly => 'Every year',
        };
    }

    /**
     * Advance a date by one cycle.
     *
     * Monthly steps clamp to the end of short months, so a rent charge dated the 31st
     * lands on the 28th in February rather than spilling into March.
     */
    public function next(CarbonInterface $from, int $interval = 1): CarbonInterface
    {
        return match ($this) {
            self::Daily => $from->copy()->addDays($interval),
            self::Weekly => $from->copy()->addWeeks($interval),
            self::Fortnightly => $from->copy()->addWeeks(2 * $interval),
            self::Monthly => $from->copy()->addMonthsNoOverflow($interval),
            self::Quarterly => $from->copy()->addMonthsNoOverflow(3 * $interval),
            self::Yearly => $from->copy()->addYearsNoOverflow($interval),
        };
    }

    /** Roughly how many times a year this fires — used to annualise subscription costs. */
    public function perYear(): float
    {
        return match ($this) {
            self::Daily => 365,
            self::Weekly => 52,
            self::Fortnightly => 26,
            self::Monthly => 12,
            self::Quarterly => 4,
            self::Yearly => 1,
        };
    }
}
