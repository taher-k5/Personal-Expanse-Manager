<?php

declare(strict_types=1);

use App\Livewire\GroupLedger;
use App\Models\ExpenseGroup;
use App\Models\GroupInvite;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/trips/{group}', GroupLedger::class)
        ->name('groups.show')
        ->can('view', 'group');

    // Claiming a seat in a group you were added to by name.
    Route::get('/invite/{token}', function (string $token) {
        $invite = GroupInvite::where('token', $token)->firstOrFail();

        abort_unless($invite->isUsable(), 410, 'This invite has already been used or has expired.');

        $member = $invite->acceptFor(auth()->user());

        return redirect()
            ->route('groups.show', $member->expense_group_id)
            ->with('status', "You are in. Everything already logged under your name is yours.");
    })->name('groups.invite');
});

/*
 * Public, read-only view of a group.
 *
 * This is the "share with anyone" path: no account, no login, nothing writable. The token
 * is the only credential, so the route is rate limited and the component is mounted with
 * readOnly set, which blocks every write path inside it.
 */
Route::get('/shared/{token}', function (string $token) {
    $group = ExpenseGroup::where('share_token', $token)
        ->where('share_enabled', true)
        ->with(['members', 'transactions.splits', 'settlements'])
        ->firstOrFail();

    return view('shared-group', ['group' => $group]);
})->middleware('throttle:30,1')->name('groups.shared');
