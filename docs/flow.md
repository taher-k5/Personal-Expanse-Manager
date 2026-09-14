# Flow

General notes on how the app fits together, for anyone (human or Claude) picking this up.

## Two surfaces

1. **Dashboard** (`/app`) — Filament panel. Personal finance: accounts, categories,
   transactions, budgets, recurring transactions. Requires login.
2. **Group ledger** (`/trips/{group}`) — Livewire. Shared expenses for a trip, event, or
   household. Requires login and group membership.

A public, read-only view of a group also exists at `/shared/{token}` — no login, share link only,
enabled per-group via `ExpenseGroup::enableSharing()`.

## Personal expense flow

1. User logs into `/app`.
2. Transactions belong to a `User`, optionally an `Account` and `Category`.
3. `AutoCategoriser` suggests a category based on `CategorisationRule`s learned from past edits.
4. `BudgetService` compares spend against active `Budget`s for the current period.
5. `RecurringTransaction`s post automatically (or remind the user) on their schedule.

## Group expense flow

1. A `User` creates an `ExpenseGroup` (trip/event/household).
2. Members are added as `GroupMember` rows — a member does **not** need an account; a bare
   name/email is enough (`user_id` is nullable).
3. Someone not yet registered can be invited via `GroupInvite`; accepting one links their new
   `User` account to their existing `GroupMember` row, so their history carries over.
4. `Transaction`s in the group are split across members via `TransactionSplit` (equal, shares,
   percentage, exact, or adjustment).
5. `GroupLedgerService` computes each member's balance; `DebtSimplifier` reduces the number of
   payments needed to settle up.
6. `Settlement`s record an actual payment between two members.
7. The group owner can enable a public share link (`/shared/{token}`) for anyone to view
   read-only, no account required.

## Adding a new model with a `user_id` column

Always add the inverse relation on `App\Models\User` in the same change (e.g. a new `hasMany`).
Several bugs in this project so far have been a `belongsTo(User::class)` added to a model without
its counterpart on `User`, which only surfaces as a `BadMethodCallException` once some page
actually calls it.

## Migrations

Migration files run in filename order. If a table's foreign key points at a table created by a
*later* migration, either reorder the migrations or add the constraint separately (via
`Schema::table(...)`) in the migration that creates the referenced table. SQLite is lenient about
this; MySQL is not — this bit us once when switching from SQLite to MySQL.
