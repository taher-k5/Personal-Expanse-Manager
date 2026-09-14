<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Somebody's place in a group. Deliberately not the same thing as a User: you can add a
 * friend by name alone and settle up with them without them ever creating an account.
 * If they sign up later, user_id gets filled in and their history comes with them.
 */
class GroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_group_id', 'user_id', 'display_name', 'email', 'phone',
        'upi_vpa', 'role', 'default_shares', 'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'default_shares' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'expense_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function paidTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'paid_by_member_id');
    }

    public function isRegistered(): bool
    {
        return $this->user_id !== null;
    }

    public function canEdit(): bool
    {
        return in_array($this->role, ['owner', 'editor'], true);
    }

    public function payoutVpa(): ?string
    {
        return $this->upi_vpa ?: $this->user?->upi_vpa;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->display_name)) ?: [];

        return strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
