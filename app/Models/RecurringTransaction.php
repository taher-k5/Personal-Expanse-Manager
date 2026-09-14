<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rent, salary, subscriptions — anything that happens on a schedule. */
class RecurringTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'account_id', 'category_id', 'name', 'type', 'amount_minor', 'currency',
        'frequency', 'interval', 'starts_on', 'ends_on', 'next_run_on', 'last_run_on',
        'auto_post', 'remind_days_before', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'frequency' => RecurrenceFrequency::class,
            'amount' => MoneyCast::class.':amount_minor,currency',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_run_on' => 'date',
            'last_run_on' => 'date',
            'auto_post' => 'boolean',
            'is_active' => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeDue(Builder $query): void
    {
        $query->where('is_active', true)->whereDate('next_run_on', '<=', now());
    }

    /**
     * Create the transaction for this cycle and move the schedule on.
     *
     * Catch-up safe: if the scheduler has not run for a week, calling this repeatedly
     * posts each missed occurrence in order rather than skipping to today.
     */
    public function post(): ?Transaction
    {
        if (! $this->is_active || $this->next_run_on === null) {
            return null;
        }

        if ($this->ends_on !== null && $this->next_run_on->gt($this->ends_on)) {
            $this->update(['is_active' => false]);

            return null;
        }

        $transaction = $this->transactions()->create([
            'user_id' => $this->user_id,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'type' => $this->type,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'booked_on' => $this->next_run_on,
            'merchant' => $this->name,
            'note' => 'Scheduled',
        ]);

        $this->update([
            'last_run_on' => $this->next_run_on,
            'next_run_on' => $this->frequency->next(
                CarbonImmutable::parse($this->next_run_on),
                $this->interval,
            ),
        ]);

        return $transaction;
    }

    /** What this costs over a year, for the subscription audit screen. */
    public function annualCost(): Money
    {
        return $this->amount->times($this->frequency->perYear() / max(1, $this->interval));
    }
}
