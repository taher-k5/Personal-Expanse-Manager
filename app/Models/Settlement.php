<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment from one person to another to clear a balance.
 *
 * Settlements are not expenses. Keeping them in their own table is what stops "Aman paid
 * Bilal 2,000" from showing up in the monthly food budget.
 */
class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_group_id', 'from_member_id', 'to_member_id', 'amount_minor', 'currency',
        'settled_on', 'method', 'status', 'payment_reference', 'upi_intent_uri',
        'confirmed_at', 'confirmed_by', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => SettlementStatus::class,
            'amount' => MoneyCast::class.':amount_minor,currency',
            'settled_on' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'expense_group_id');
    }

    public function fromMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'from_member_id');
    }

    public function toMember(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'to_member_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Mark the money as received.
     *
     * The app cannot see into anyone's bank account, so a human vouches for this. The payee
     * confirming is the meaningful signal; the payer confirming is a claim, which is why
     * status and confirmed_by are both recorded.
     */
    public function confirm(User $by): self
    {
        $this->forceFill([
            'status' => SettlementStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmed_by' => $by->id,
        ])->save();

        return $this;
    }

    public function describe(): string
    {
        return sprintf(
            '%s paid %s %s',
            $this->fromMember->display_name,
            $this->toMember->display_name,
            $this->amount->format(),
        );
    }
}
