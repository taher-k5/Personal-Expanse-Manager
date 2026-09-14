<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Money;

/** Saving towards something: a trip, a laptop, three months of runway. */
class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'account_id', 'name', 'target_minor', 'saved_minor',
        'currency', 'target_date', 'colour', 'achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'target' => MoneyCast::class.':target_minor,currency',
            'saved' => MoneyCast::class.':saved_minor,currency',
            'target_date' => 'date',
            'achieved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function progress(): float
    {
        return $this->target_minor > 0
            ? min(1.0, round($this->saved_minor / $this->target_minor, 4))
            : 0.0;
    }

    /** How much to put aside each month to arrive on time. */
    public function monthlyContribution(): ?Money
    {
        if ($this->target_date === null) {
            return null;
        }

        $months = max(1, (int) ceil(CarbonImmutable::now()->diffInMonths($this->target_date)));
        $remaining = max(0, $this->target_minor - $this->saved_minor);

        return new Money(intdiv($remaining, $months), $this->currency);
    }
}
