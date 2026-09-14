<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's share of one shared expense.
 */
class TransactionSplit extends Model
{
    use HasFactory;

    protected $fillable = ['transaction_id', 'group_member_id', 'amount_minor', 'split_input'];

    protected function casts(): array
    {
        return [
            'split_input' => 'decimal:6',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }

    /** Currency lives on the parent transaction, so the cast is resolved by hand here. */
    public function getAmountAttribute(): Money
    {
        return new Money(
            (int) $this->amount_minor,
            $this->transaction?->currency ?? 'INR',
        );
    }
}
