<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GroupType;
use App\Services\GroupLedgerService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A trip, an event, a shared flat — anywhere more than one person's money is involved.
 */
class ExpenseGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id', 'name', 'type', 'description', 'currency', 'starts_on', 'ends_on',
        'destination', 'cover_path', 'share_token', 'share_enabled', 'simplify_debts', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => GroupType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'settled_at' => 'datetime',
            'share_enabled' => 'boolean',
            'simplify_debts' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(GroupInvite::class);
    }

    public function total(): Money
    {
        return new Money((int) $this->transactions()->sum('amount_minor'), $this->currency);
    }

    /** Per-head cost, the number everyone asks for at the end of a trip. */
    public function perPerson(): Money
    {
        $count = max(1, $this->members()->count());

        return new Money(intdiv($this->total()->minor, $count), $this->currency);
    }

    public function balances()
    {
        return app(GroupLedgerService::class)->balances($this);
    }

    public function settlementPlan(): array
    {
        return app(GroupLedgerService::class)->settlementPlan($this);
    }

    public function isSettled(): bool
    {
        return $this->balances()->every(fn (Money $balance): bool => $balance->isZero());
    }

    /**
     * Turn on the public read-only link. Anyone with the URL can see the ledger but not
     * change it — the way you send a trip summary to five friends who will never sign up.
     */
    public function enableSharing(): self
    {
        $this->forceFill([
            'share_token' => $this->share_token ?? Str::random(40),
            'share_enabled' => true,
        ])->save();

        return $this;
    }

    public function disableSharing(): self
    {
        $this->forceFill(['share_enabled' => false])->save();

        return $this;
    }

    public function shareUrl(): ?string
    {
        return $this->share_enabled && $this->share_token
            ? route('groups.shared', $this->share_token)
            : null;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
