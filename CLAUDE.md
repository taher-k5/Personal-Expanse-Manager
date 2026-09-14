# expense-manager

A personal expense tracker and shared-trip ledger built on Laravel + Filament + Livewire.

## Stack

- Laravel 12, PHP 8.2
- Filament 5 — admin/dashboard panel, mounted at `/app` (not `/admin`)
- Livewire 4 — the user-facing (non-admin) pages
- MySQL 8 (via Docker) — SQLite for a quick local run without Docker
- Vite + Tailwind 4 for frontend assets

## Structure

- `app/Filament/` — the `/app` dashboard: resources (Transactions, Budgets, ExpenseGroups) and widgets
- `app/Livewire/` — user-facing pages: `GroupLedger` (`/trips/{group}`), `QuickAdd`
- `app/Models/` — core domain: `User`, `Account`, `Category`, `Transaction`, `Budget`, `Goal`,
  `RecurringTransaction`, `CategorisationRule`, `ExpenseGroup`, `GroupMember`, `GroupInvite`,
  `Settlement`, `TransactionSplit`
- `app/Services/` — business logic: `BudgetService`, `GroupLedgerService`, `DebtSimplifier`,
  `InsightService`, `AutoCategoriser`
- `routes/web.php` — `/`, `/trips/{group}`, `/invite/{token}`, `/shared/{token}` (public read-only)
- `database/seeders/AdminUserSeeder.php` — creates the dashboard admin from `ADMIN_*` env vars
- `docker/entrypoint.sh` — container boot: migrate, seed, cache, serve

## Domain model

A `User` owns/joins `ExpenseGroup`s (trips, events, shared flats) through `GroupMember` rows.
Members don't have to be registered users — they can be a bare name/email, and get linked to a
`User` later via `GroupInvite`. `Transaction`s split across members via `TransactionSplit`, and
`Settlement`s record who paid whom back. `User` also has its own personal `Account`s, `Category`s,
`Budget`s, `RecurringTransaction`s, and `CategorisationRule`s independent of any group.

When adding a `belongsTo(User::class)` on a new model, remember to add the inverse relation on
`User` too — this has been a recurring bug (`groups()`, `budgets()`, `memberships()`, etc. were all
missing until something hit them at runtime).

## Running locally

**Docker (recommended, uses MySQL):**
```
docker compose up -d --build
```
Serves at http://127.0.0.1:8000. Dashboard login at `/app/login` — see `ADMIN_EMAIL` /
`ADMIN_PASSWORD` in `.env`.

**Without Docker:**
```
composer install && npm install
php artisan migrate --seed
composer run dev
```

## Login / admin dashboard

The Filament panel lives at `/app` (`app/Providers/Filament/AdminPanelProvider.php`). The admin
account is created by `database/seeders/AdminUserSeeder.php`, driven by `ADMIN_NAME`,
`ADMIN_EMAIL`, `ADMIN_PASSWORD` in `.env` (defaults: `admin@example.com` / `change-me-please` —
change this before deploying anywhere real).

## Docs

See `docs/flow.md` for user-facing flow notes and `docs/deployment.md` for deployment notes.
