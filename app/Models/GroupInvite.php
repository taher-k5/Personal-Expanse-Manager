<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A one-use link that attaches a signed-in account to an existing placeholder member. */
class GroupInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_group_id', 'group_member_id', 'invited_by',
        'email', 'phone', 'token', 'expires_at', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invite): void {
            $invite->token ??= Str::random(64);
            $invite->expires_at ??= now()->addDays(30);
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'expense_group_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at?->isFuture();
    }

    /** Claim the placeholder seat. Their whole share history comes with it. */
    public function acceptFor(User $user): GroupMember
    {
        $member = $this->member ?? $this->group->members()->create([
            'display_name' => $user->name,
            'email' => $user->email,
        ]);

        $member->update([
            'user_id' => $user->id,
            'email' => $member->email ?? $user->email,
            'joined_at' => now(),
        ]);

        $this->update(['accepted_at' => now()]);

        return $member;
    }

    public function url(): string
    {
        return route('groups.invite', $this->token);
    }
}
