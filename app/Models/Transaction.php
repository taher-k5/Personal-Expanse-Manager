<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\SplitMethod;
use App\Enums\TransactionType;
use App\Services\SplitCalculator;
use App\Services\InsightService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'account_id', 'category_id', 'expense_group_id', 'paid_by_member_id',
        'type', 'amount', 'amount_minor', 'currency', 'base_amount_minor', 'exchange_rate',
        'booked_on', 'merchant', 'normalised_merchant', 'note', 'receipt_path',
        'transfer_account_id', 'transfer_group_uuid', 'split_method',
        'recurring_transaction_id', 'is_reimbursable', 'exclude_from_budget', 'import_hash',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'split_method' => SplitMethod::class,
            'amount' => MoneyCast::class.':amount_minor,currency',
            'booked_on' => 'date',
            'exchange_rate' => 'decimal:8',
            'is_reimbursable' => 'boolean',
            'exclude_from_budget' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $transaction): void {
            $transaction->normalised_merchant = InsightService::normaliseMerchant($transaction->merchant);
        });

        static::saved(fn (self $t) => $t->account?->recalculateBalance());
        static::deleted(fn (self $t) => $t->account?->recalculateBalance());
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'expense_group_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(GroupMember::class, 'paid_by_member_id');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function scopeBetween(Builder $query, $from, $to): void
    {
        $query->whereBetween('booked_on', [$from, $to]);
    }

    public function scopeSpending(Builder $query): void
    {
        $query->where('type', TransactionType::Expense)->where('exclude_from_budget', false);
    }

    /** Value in the user's home currency — what every total and budget is measured in. */
    public function baseAmount(): Money
    {
        return new Money(
            $this->base_amount_minor ?? $this->amount_minor,
            $this->user?->base_currency ?? $this->currency,
        );
    }

    /** Value in the group's currency, for a shared expense paid abroad. */
    public function groupAmount(): Money
    {
        return new Money($this->amount_minor, $this->currency);
    }

    public function isShared(): bool
    {
        return $this->expense_group_id !== null && $this->split_method !== SplitMethod::None;
    }

    /** What the signed-in user personally owes on this line, after the split. */
    public function shareFor(?GroupMember $member): Money
    {
        if ($member === null) {
            return $this->groupAmount();
        }

        return $this->splits->firstWhere('group_member_id', $member->id)?->amount
            ?? Money::zero($this->currency);
    }

    /**
     * Replace the split rows for this transaction.
     *
     * Splits are always written as a set, never patched one at a time, so the total cannot
     * drift away from the transaction amount between two saves.
     *
     * @param  array<int, float|int|string>  $input  keyed by group member id
     */
    public function applySplit(SplitMethod $method, array $input): self
    {
        $amounts = SplitCalculator::for($method, $this->groupAmount(), $input);

        if ($amounts !== []) {
            SplitCalculator::assertBalanced($this->groupAmount(), $amounts);
        }

        $this->splits()->delete();

        foreach ($amounts as $memberId => $amount) {
            $this->splits()->create([
                'group_member_id' => $memberId,
                'amount_minor' => $amount->minor,
                'split_input' => is_numeric($input[$memberId] ?? null) ? (float) $input[$memberId] : null,
            ]);
        }

        $this->forceFill(['split_method' => $method])->save();

        return $this->load('splits');
    }
}
