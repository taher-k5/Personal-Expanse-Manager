{{-- The add sheet. Amount is the hero: it is the only thing that is always required, and
     it is sized so it can be read and checked without looking closely. --}}
<form wire:submit="save" class="panel p-5 space-y-6">

    <div>
        <label for="amount" class="block text-sm text-muted mb-1">Amount</label>
        <div class="flex items-baseline gap-2">
            <span class="figure text-2xl text-muted">
                {{ $this->group()?->currency ?? auth()->user()->base_currency }}
            </span>
            <input
                id="amount"
                type="text"
                inputmode="decimal"
                autocomplete="off"
                placeholder="0.00"
                wire:model.live.debounce.400ms="amount"
                class="figure w-full bg-transparent text-figure font-semibold placeholder:text-rule focus:outline-none"
            >
        </div>
        @error('amount') <p class="mt-2 text-sm text-debit">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="merchant" class="block text-sm text-muted mb-1">Paid to</label>
            <input id="merchant" type="text" wire:model.blur="merchant" placeholder="Blue Tokai"
                   class="w-full rounded-lg border border-rule bg-surface px-3 py-2">
        </div>

        <div>
            <label for="bookedOn" class="block text-sm text-muted mb-1">Date</label>
            <input id="bookedOn" type="date" wire:model="bookedOn"
                   class="w-full rounded-lg border border-rule bg-surface px-3 py-2">
        </div>
    </div>

    {{-- Categories as chips rather than a select: on a phone, one tap beats a dropdown,
         and the suggested one is already highlighted. --}}
    <fieldset>
        <legend class="text-sm text-muted mb-2">Category</legend>
        <div class="flex flex-wrap gap-2">
            @foreach ($this->categories() as $category)
                <button type="button" wire:click="$set('categoryId', {{ $category->id }})"
                        class="chip border {{ $categoryId === $category->id ? 'border-action text-action bg-action-soft' : 'border-transparent' }}">
                    <span class="size-2 rounded-full" style="background: {{ $category->colour }}"></span>
                    {{ $category->name }}
                </button>
            @endforeach
        </div>
    </fieldset>

    @if ($this->groups()->isNotEmpty())
        <fieldset class="border-t border-rule pt-4">
            <legend class="text-sm text-muted mb-2">Part of a trip or event?</legend>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="selectGroup(null)"
                        class="chip border {{ $groupId === null ? 'border-action text-action' : 'border-transparent' }}">
                    Just mine
                </button>
                @foreach ($this->groups() as $group)
                    <button type="button" wire:click="selectGroup({{ $group->id }})"
                            class="chip border {{ $groupId === $group->id ? 'border-action text-action' : 'border-transparent' }}">
                        {{ $group->name }}
                    </button>
                @endforeach
            </div>
        </fieldset>
    @endif

    @if ($this->group())
        @php($group = $this->group())
        <div class="space-y-4 border-t border-rule pt-4">

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="paidBy" class="block text-sm text-muted mb-1">Who paid</label>
                    <select id="paidBy" wire:model.live="paidByMemberId"
                            class="w-full rounded-lg border border-rule bg-surface px-3 py-2">
                        @foreach ($group->members as $member)
                            <option value="{{ $member->id }}">{{ $member->display_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="splitMethod" class="block text-sm text-muted mb-1">How to divide it</label>
                    <select id="splitMethod" wire:model.live="splitMethod"
                            class="w-full rounded-lg border border-rule bg-surface px-3 py-2">
                        @foreach (\App\Enums\SplitMethod::cases() as $method)
                            @continue($method === \App\Enums\SplitMethod::None)
                            <option value="{{ $method->value }}">{{ $method->getLabel() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-muted">{{ \App\Enums\SplitMethod::from($splitMethod)->hint() }}</p>
                </div>
            </div>

            {{-- The running preview is the point of this block: nobody should have to save
                 an expense to find out what it does to the ledger. --}}
            <ul>
                @foreach ($group->members as $member)
                    @php($share = ($this->preview()[$member->id] ?? null))
                    <li class="ledger-row">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" wire:model.live="participants.{{ $member->id }}"
                                   class="size-4 accent-[var(--color-action)]">
                            <span class="avatar">{{ $member->initials() }}</span>
                            <span>{{ $member->display_name }}</span>
                        </label>

                        <div class="flex items-center gap-3">
                            @if (\App\Enums\SplitMethod::from($splitMethod)->needsInputPerMember())
                                <input type="text" inputmode="decimal"
                                       wire:model.live.debounce.400ms="splitInputs.{{ $member->id }}"
                                       aria-label="Split value for {{ $member->display_name }}"
                                       class="figure w-20 rounded-lg border border-rule px-2 py-1 text-sm"
                                       @disabled(! ($participants[$member->id] ?? false))>
                            @endif
                            <span class="figure w-28 {{ $share?->isZero() ? 'text-muted' : 'figure-out' }}">
                                {{ $share?->format() ?? '—' }}
                            </span>
                        </div>
                    </li>
                @endforeach
            </ul>

            @error('splitInputs') <p class="text-sm text-debit">{{ $message }}</p> @enderror
        </div>
    @endif

    <div class="flex items-center justify-end gap-3 border-t border-rule pt-4">
        <button type="submit" class="btn w-full sm:w-auto">
            <span wire:loading.remove wire:target="save">Save expense</span>
            <span wire:loading wire:target="save">Saving</span>
        </button>
    </div>
</form>
