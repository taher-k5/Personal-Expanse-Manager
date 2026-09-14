<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TransactionType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'type', 'currency', 'opening_balance_minor',
        'current_balance_minor', 'credit_limit_minor', 'statement_day',
        'colour', 'icon', 'is_archived', 'exclude_from_net_worth',
    ];

    protected function casts(): array
    {
        return [
            'openingBalance' => MoneyCast::class.':opening_balance_minor,currency',
            'balance' => MoneyCast::class.':current_balance_minor,currency',
            'is_archived' => 'boolean',
            'exclude_from_net_worth' => 'boolean',
        ];
    }

    protected $appends = ['balance'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    /**
     * Recompute the balance from the transaction history.
     *
     * The denormalised column exists so lists stay fast; this is the source of truth it is
     * derived from, and it runs after any write that could move the balance.
     */
    public function recalculateBalance(): self
    {
        $in = (int) $this->transactions()
            ->where('type', TransactionType::Income)
            ->sum('amount_minor');

        $out = (int) $this->transactions()
            ->whereIn('type', [TransactionType::Expense, TransactionType::Transfer])
            ->sum('amount_minor');

        $transfersIn = (int) Transaction::query()
            ->where('transfer_account_id', $this->id)
            ->where('type', TransactionType::Transfer)
            ->sum('amount_minor');

        $this->forceFill([
            'current_balance_minor' => $this->opening_balance_minor + $in - $out + $transfersIn,
        ])->save();

        return $this;
    }

    /** Credit cards report headroom rather than a balance. */
    public function availableCredit(): ?Money
    {
        if ($this->credit_limit_minor === null) {
            return null;
        }

        return new Money($this->credit_limit_minor + $this->current_balance_minor, $this->currency);
    }
}
