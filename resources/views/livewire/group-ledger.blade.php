{{-- The trip screen. The hero is the one sentence people open this page to read: whether
     they are up or down, and by how much. --}}
<div class="space-y-8" x-data="{ payment: null }"
     @settlement-started.window="payment = $event.detail">

    <header class="panel p-6">
        <p class="text-sm text-muted">{{ $group->name }}</p>

        @if ($this->me())
            @php($position = $this->myPosition())
            <p class="mt-2 text-figure font-semibold {{ $position->isPositive() ? 'figure-in' : ($position->isNegative() ? 'figure-out' : '') }}">
                {{ $position->isZero() ? 'All square' : $position->absolute()->format() }}
            </p>
            <p class="mt-1 text-muted">
                @if ($position->isZero())
                    You do not owe anyone here, and nobody owes you.
                @elseif ($position->isPositive())
                    is owed back to you
                @else
                    is what you still owe
                @endif
            </p>
        @else
            <p class="mt-2 text-figure font-semibold">{{ $group->total()->format() }}</p>
            <p class="mt-1 text-muted">spent across {{ $group->members->count() }} people</p>
        @endif

        <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-rule pt-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs text-muted">Total</dt>
                <dd class="figure text-left">{{ $group->total()->format() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Per person</dt>
                <dd class="figure text-left">{{ $group->perPerson()->format() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Entries</dt>
                <dd class="figure text-left">{{ $this->expenses()->count() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">People</dt>
                <dd class="figure text-left">{{ $group->members->count() }}</dd>
            </div>
        </dl>
    </header>

    <section>
        <h2 class="mb-3 text-lg font-semibold">Where everyone stands</h2>
        <ul class="panel">
            @foreach ($this->summary() as $row)
                <li class="ledger-row">
                    <div class="flex items-center gap-3">
                        <span class="avatar">{{ $row['member']->initials() }}</span>
                        <div>
                            <p class="font-medium">{{ $row['member']->display_name }}</p>
                            <p class="text-xs text-muted">
                                paid {{ $row['paid']->format() }} · share {{ $row['share']->format() }}
                            </p>
                        </div>
                    </div>
                    <span class="figure {{ $row['net']->isPositive() ? 'figure-in' : ($row['net']->isNegative() ? 'figure-out' : 'text-muted') }}">
                        {{ $row['net']->isZero() ? 'settled' : $row['net']->absolute()->format() }}
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($this->plan() !== [])
        <section>
            <h2 class="text-lg font-semibold">Settling up</h2>
            <p class="mb-3 text-sm text-muted">
                {{ $group->simplify_debts
                    ? 'Reduced to the fewest payments that clear the group.'
                    : 'Kept between the people who actually shared each expense.' }}
            </p>

            <ul class="panel">
                @foreach ($this->plan() as $transfer)
                    <li class="ledger-row">
                        <div class="flex items-center gap-3">
                            <span class="avatar">{{ $transfer['from']->initials() }}</span>
                            <p>
                                <span class="font-medium">{{ $transfer['from']->display_name }}</span>
                                pays
                                <span class="font-medium">{{ $transfer['to']->display_name }}</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="figure">{{ $transfer['amount']->format() }}</span>
                            @unless ($readOnly)
                                <button type="button" class="btn btn-quiet text-sm"
                                        wire:click="startSettlement({{ $transfer['from']->id }}, {{ $transfer['to']->id }}, {{ $transfer['amount']->minor }})">
                                    {{ $transfer['to']->payoutVpa() ? 'Pay with UPI' : 'Mark as paid' }}
                                </button>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- The UPI hand-off. The link opens Google Pay or whichever UPI app the phone
         defaults to, with the payee and amount already filled in. We cannot see whether
         the payment went through, so the ledger only moves when a person says it did. --}}
    <template x-if="payment">
        <div class="panel p-5 space-y-3">
            <h3 class="font-semibold">Finish the payment in your UPI app</h3>
            <p class="text-sm text-muted">
                Your bank app takes it from here. Come back and confirm once it goes through.
            </p>
            <div class="flex flex-wrap gap-3">
                <a class="btn" :href="payment.uri">Open UPI app</a>
                <button type="button" class="btn btn-quiet"
                        x-on:click="$wire.confirmSettlement(payment.id); payment = null">
                    I have paid this
                </button>
            </div>
        </div>
    </template>

    <section>
        <h2 class="mb-3 text-lg font-semibold">Everything spent</h2>
        <ul class="panel">
            @forelse ($this->expenses() as $expense)
                <li class="ledger-row">
                    <div>
                        <p class="font-medium">{{ $expense->merchant ?? $expense->category?->name ?? 'Expense' }}</p>
                        <p class="text-xs text-muted">
                            {{ $expense->booked_on->format('j M') }}
                            · {{ $expense->paidBy?->display_name ?? 'Unknown' }} paid
                            · split {{ $expense->splits->count() }} ways
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="figure">{{ $expense->groupAmount()->format() }}</p>
                        @if ($this->me())
                            <p class="figure text-xs text-muted">
                                your share {{ $expense->shareFor($this->me())->format() }}
                            </p>
                        @endif
                    </div>
                </li>
            @empty
                <li class="p-8 text-center text-muted">
                    Nothing logged yet. Add the first expense and the balances will work themselves out.
                </li>
            @endforelse
        </ul>
    </section>
</div>
