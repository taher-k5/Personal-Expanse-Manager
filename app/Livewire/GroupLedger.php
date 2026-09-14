<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SettlementStatus;
use App\Models\ExpenseGroup;
use App\Models\GroupMember;
use App\Models\Settlement;
use App\Services\GroupLedgerService;
use App\Services\UpiPaymentService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The trip screen: what was spent, where everyone stands, and how to clear it.
 */
class GroupLedger extends Component
{
    public ExpenseGroup $group;

    /** Set when the reader arrived through a public share link rather than a login. */
    public bool $readOnly = false;

    public ?int $payingMemberId = null;

    public function mount(ExpenseGroup $group, bool $readOnly = false): void
    {
        $this->group = $group;
        $this->readOnly = $readOnly;
    }

    #[Computed]
    public function ledger(): GroupLedgerService
    {
        return app(GroupLedgerService::class);
    }

    #[Computed]
    public function me(): ?GroupMember
    {
        return $this->readOnly
            ? null
            : $this->group->members->firstWhere('user_id', auth()->id());
    }

    #[Computed]
    public function myPosition(): Money
    {
        return $this->me()
            ? $this->ledger()->positionFor($this->group, $this->me())
            : Money::zero($this->group->currency);
    }

    #[Computed]
    public function summary()
    {
        return $this->ledger()->summary($this->group);
    }

    #[Computed]
    public function plan(): array
    {
        return $this->ledger()->settlementPlan($this->group);
    }

    #[Computed]
    public function expenses()
    {
        return $this->group->transactions()
            ->with(['splits.member', 'paidBy', 'category'])
            ->orderByDesc('booked_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Record an intended payment and hand back a UPI request the payer can act on.
     * No money moves here — the payer confirms in their own bank app.
     */
    public function startSettlement(int $fromMemberId, int $toMemberId, int $amountMinor): void
    {
        abort_if($this->readOnly, 403);

        $settlement = Settlement::create([
            'expense_group_id' => $this->group->id,
            'from_member_id' => $fromMemberId,
            'to_member_id' => $toMemberId,
            'amount_minor' => $amountMinor,
            'currency' => $this->group->currency,
            'settled_on' => today(),
            'method' => 'upi',
            'status' => SettlementStatus::Pending,
        ]);

        app(UpiPaymentService::class)->attachTo($settlement);

        $this->payingMemberId = $toMemberId;
        $this->dispatch('settlement-started', id: $settlement->id, uri: $settlement->upi_intent_uri);
    }

    public function confirmSettlement(int $settlementId): void
    {
        abort_if($this->readOnly, 403);

        $settlement = $this->group->settlements()->findOrFail($settlementId);
        $settlement->confirm(auth()->user());

        unset($this->plan, $this->summary, $this->myPosition);
    }

    #[On('expense-added')]
    public function refreshLedger(): void
    {
        unset($this->expenses, $this->plan, $this->summary, $this->myPosition);
    }

    public function render(): View
    {
        return view('livewire.group-ledger');
    }
}
