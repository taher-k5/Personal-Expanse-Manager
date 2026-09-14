<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SplitMethod;
use App\Enums\TransactionType;
use App\Models\ExpenseGroup;
use App\Services\SplitCalculator;
use App\Services\AutoCategoriser;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The add-an-expense sheet.
 *
 * Optimised for the moment it is actually used: standing at a counter, one thumb, phone in
 * the other hand. Amount first, everything else optional, one tap to save.
 */
class QuickAdd extends Component
{
    public string $amount = '';

    public string $merchant = '';

    public ?int $categoryId = null;

    public ?int $accountId = null;

    public string $bookedOn = '';

    public ?int $groupId = null;

    public string $splitMethod = SplitMethod::Equal->value;

    /** @var array<int, bool> group member id => included in the split */
    public array $participants = [];

    /** @var array<int, string> group member id => weight, percent or exact amount */
    public array $splitInputs = [];

    public ?int $paidByMemberId = null;

    public function mount(?int $groupId = null): void
    {
        $this->bookedOn = today()->toDateString();
        $this->accountId = auth()->user()->accounts()->active()->value('id');

        if ($groupId !== null) {
            $this->selectGroup($groupId);
        }
    }

    #[Computed]
    public function categories()
    {
        return auth()->user()->categories()->expenses()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function groups()
    {
        return ExpenseGroup::query()
            ->whereIn('id', auth()->user()->memberships()->pluck('expense_group_id'))
            ->whereNull('settled_at')
            ->latest()
            ->get();
    }

    #[Computed]
    public function group(): ?ExpenseGroup
    {
        return $this->groupId ? ExpenseGroup::with('members')->find($this->groupId) : null;
    }

    /** Live preview of who owes what, recalculated on every keystroke. */
    #[Computed]
    public function preview(): array
    {
        $group = $this->group();

        if ($group === null || $this->amount === '') {
            return [];
        }

        try {
            $total = Money::fromMajor($this->amount, $group->currency);

            return SplitCalculator::for(
                SplitMethod::from($this->splitMethod),
                $total,
                $this->splitPayload(),
            );
        } catch (\Throwable $e) {
            // A half-typed percentage is not an error worth shouting about; the preview
            // just stays empty until the numbers make sense.
            return [];
        }
    }

    public function selectGroup(?int $groupId): void
    {
        $this->groupId = $groupId;
        $group = $this->group();

        if ($group === null) {
            $this->participants = [];

            return;
        }

        $this->participants = $group->members->mapWithKeys(fn ($m): array => [$m->id => true])->all();
        $this->splitInputs = $group->members->mapWithKeys(fn ($m): array => [$m->id => (string) $m->default_shares])->all();
        $this->paidByMemberId = $group->members->firstWhere('user_id', auth()->id())?->id;
    }

    public function updatedMerchant(string $value): void
    {
        if ($this->categoryId === null) {
            $this->categoryId = app(AutoCategoriser::class)->suggest(auth()->user(), $value);
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'merchant' => ['nullable', 'string', 'max:120'],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'accountId' => ['nullable', 'exists:accounts,id'],
            'bookedOn' => ['required', 'date'],
            'groupId' => ['nullable', 'exists:expense_groups,id'],
        ]);

        $group = $this->group();
        $currency = $group?->currency ?? auth()->user()->base_currency;
        $money = Money::fromMajor($data['amount'], $currency);

        $transaction = auth()->user()->transactions()->create([
            'account_id' => $this->accountId,
            'category_id' => $this->categoryId,
            'expense_group_id' => $this->groupId,
            'paid_by_member_id' => $this->paidByMemberId,
            'type' => TransactionType::Expense,
            'amount_minor' => $money->minor,
            'currency' => $currency,
            'booked_on' => $this->bookedOn,
            'merchant' => $this->merchant ?: null,
        ]);

        if ($group !== null) {
            try {
                $transaction->applySplit(SplitMethod::from($this->splitMethod), $this->splitPayload());
            } catch (\InvalidArgumentException $e) {
                $transaction->delete();

                throw ValidationException::withMessages(['splitInputs' => $e->getMessage()]);
            }
        }

        $this->dispatch('expense-added', id: $transaction->id);
        $this->reset(['amount', 'merchant', 'categoryId']);
    }

    /** @return array<int, float|string> */
    private function splitPayload(): array
    {
        $included = array_keys(array_filter($this->participants));

        if (SplitMethod::from($this->splitMethod) === SplitMethod::Equal) {
            return array_fill_keys($included, 1);
        }

        return collect($included)
            ->mapWithKeys(fn (int $id): array => [$id => $this->splitInputs[$id] ?? 0])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.quick-add');
    }
}
