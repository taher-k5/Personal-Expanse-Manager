# Expense manager

A personal money app: monthly spending and budgets for yourself, shared ledgers for trips
and events, and a one-tap UPI settle-up when it is time to square up.

Laravel + Filament on the admin side, Livewire for the screens you use every day.

---

## What is in here

This is the domain layer and the interface layer, meant to be dropped into a fresh Laravel
application — not a standalone repository. There is no `vendor/`, no `bootstrap/`, and no
framework skeleton. Setup is below.

```
app/
  Support/Money.php          Integer-minor-unit money. The foundation everything sits on.
  Casts/MoneyCast.php        Pairs an amount column with a currency column on any model.
  Enums/                     Transaction types, split methods, group types, recurrence.
  Models/                    13 models: transactions, groups, splits, settlements, budgets…
  Services/
    SplitCalculator.php      Five ways to divide a bill, all of which always balance.
    DebtSimplifier.php       Reduces a tangle of debts to the fewest payments.
    GroupLedgerService.php   Who paid, who owes, and the plan to clear it.
    BudgetService.php        Budgets, rollover envelopes, pace projection.
    InsightService.php       Trends, category breakdowns, subscription detection.
    UpiPaymentService.php    UPI / Google Pay intent links and QR codes.
    AutoCategoriser.php      Guesses categories and learns from your corrections.
  Filament/                  Admin resources for transactions, trips, budgets + 3 widgets.
  Livewire/                  QuickAdd (the add sheet) and GroupLedger (the trip screen).
database/migrations/         8 migrations covering the full schema.
tests/Unit/                  The money and split-maths tests.
```

---

## The one design decision everything else follows from

**Money is never a float.** Every amount is an integer count of the smallest unit — paise,
cents — paired with a currency code. `0.1 + 0.2` is not `0.3` in binary floating point, and
a rupee that vanishes when six people split a bill is the first bug your users will find.

`Money::allocate()` is the primitive. It divides an amount across weighted buckets using the
largest-remainder method, so the parts always sum back to the whole, and it breaks ties
deterministically so the same split never shifts by a paisa between two page loads. Every
split method in the app is a thin wrapper around it.

The tests in `tests/Unit/MoneySplitTest.php` are the contract. If you change anything in
`Money` or `SplitCalculator`, those are the ones that matter.

---

## Features

**Your own money**

- Transactions across multiple accounts (cash, bank, cards, wallets), with balances derived
  from history rather than trusted as a stored number.
- Categories with sub-categories, colours and an essential/flexible flag, so the app can
  show you fixed costs separately from the spending you can actually change.
- Budgets per category or one for everything, monthly/weekly/yearly, with **rollover
  envelopes** — an underspent month makes the next one roomier, and an overspend follows you.
- **Pace projection.** Being under budget on the 9th means nothing. The app projects where
  the month lands at your current rate and flags budgets heading for trouble.
- **Safe to spend today** — what is left, divided by the days remaining.
- Recurring transactions (rent, salary, subscriptions) posted by a scheduled command, with
  catch-up behaviour so a scheduler outage does not silently skip a month.
- **Subscription detection**: finds merchants charging similar amounts at steady intervals
  and shows the annualised cost. This is how people find the gym membership they stopped
  using in March.
- Auto-categorisation that learns. Correct the same merchant twice and it writes itself a rule.
- Savings goals with the monthly contribution needed to hit the date.
- Multi-currency: spend abroad in the local currency, everything tallies in your home one.

**Shared money**

- Trips, events, shared homes, projects — any group where more than one wallet is involved.
- **Members do not need accounts.** Add a friend by name and settle up with them anyway. If
  they sign up later, an invite link claims their seat and their whole history comes with it.
- Five split methods: equally, by shares (a couple sharing a room gets 2), by percentage, by
  exact amounts, and "equally plus extras" for the dinner where two people ordered cocktails.
- **Debt simplification.** Aman owes Bilal 500 and Bilal owes Chetna 500 becomes one payment
  from Aman to Chetna. Per-group setting, because some groups find it confusing and would
  rather keep debts between the people who actually shared the expense.
- Read-only share links, so you can send the trip ledger to five friends who will never
  install anything.
- UPI settle-up (below).

---

## About the Google Pay integration

Worth being straight about, because it shapes the design.

**What works:** the app generates a standard NPCI UPI intent URI and a scannable QR for it.
On a phone, tapping it opens Google Pay, PhonePe, Paytm or whatever the default UPI app is,
with payee, amount and a reference already filled in. The payer confirms with their PIN.
No registration, no API key, no merchant account, no fees. `UpiPaymentService` builds both a
generic `upi://pay?…` link and a `tez://upi/pay?…` one that goes straight to Google Pay.

**What does not work, and cannot:** there is no public Google Pay peer-to-peer API. Nothing
you can build will move money between two individuals programmatically, and nothing will
tell your app that a payment succeeded. Programmatic collection needs a licensed PSP
(Razorpay, Cashfree, PhonePe Business) with you registered as a merchant — wrong shape, and
usually against their terms, for splitting a dinner bill among friends.

**So the flow is:** generate the intent → they pay in their own app → a human marks it
confirmed. `Settlement` records both the status and *who* confirmed it, because the payee
confirming receipt is a meaningfully stronger signal than the payer claiming they sent it.

If you later want real reconciliation, the honest routes are an Account Aggregator
(Setu, Finvu) for read-only bank data with consent, or a PSP payment link if this ever
becomes a business rather than a personal tool. Both are significant scope.

---

## Design direction for the Livewire screens

The tokens are in `resources/css/app.css`. Three ideas carry the whole interface:

- **The accountant's convention.** Red is money leaving. Green-black is money arriving.
  Nothing else in the app uses either colour, so colour carries real information on a screen
  that is otherwise a long list of numbers. Blue means "tappable" and means nothing else.
- **Public Sans and Spline Sans Mono.** Public Sans was drawn for the US Treasury's public
  financial interfaces — its figures are unambiguous at small sizes, which matters when the
  difference between 1,100 and 1,700 is somebody's rent. Every amount is set in tabular mono
  and right-aligned, so columns line up on the decimal. Nothing else uses mono.
- **A ledger, not a card deck.** Transactions are separated by hairline rules inside one
  panel, not chopped into forty shadowed cards. Forty transactions should read as one
  continuous document.

The add sheet is built for the moment it is actually used: standing at a counter, one thumb.
Amount is the hero, everything else is optional, categories are tap-targets rather than a
dropdown, and the split preview updates as you type so nobody has to save an expense to find
out what it does to the ledger.

---

## Setup

```bash
composer create-project laravel/laravel expense-manager
cd expense-manager

composer require filament/filament:"^5.0" livewire/livewire chillerlan/php-qrcode
php artisan filament:install --panels

# copy app/, database/, resources/, routes/ and tests/ from this package over the skeleton
php artisan migrate

npm install && npm run dev
php artisan serve
```

Fonts — add to your layout head:

```html
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=public-sans:400,500,600,700|spline-sans-mono:400,600" rel="stylesheet">
```

Scheduler, for recurring transactions and budget alerts — in `routes/console.php`:

```php
Schedule::call(function () {
    App\Models\RecurringTransaction::due()->get()->each->post();
})->dailyAt('00:15');
```

Seed starter categories on signup:

```php
app(Database\Seeders\DefaultCategoriesSeeder::class)->forUser($user);
```

---

## Before you ship it

A few things I want to flag honestly rather than leave you to find:

1. **None of this has been executed.** There was no PHP runtime available while writing it,
   so treat it as a careful draft: run `composer install && ./vendor/bin/pest` and expect to
   fix a handful of things. The unit tests are the fastest way to find them, because they
   need no database.
2. **Filament 5 API.** Written against the v5 conventions — resources delegating to `Schemas/`
   and `Tables/` classes, `form(Schema $schema): Schema`, `recordActions()`/`toolbarActions()`.
   Worth checking the `Repeater` and `Section` namespaces against the docs for the exact
   patch you install; those moved between v3 and v4.
3. **Authorisation.** Resources scope by `user_id`, and the group routes reference a policy
   that is not written yet. `app/Policies/` is empty on purpose — write `ExpenseGroupPolicy`
   before this touches real data, or the `->can('view', 'group')` on the trip route will fail.
4. **The share link is a bearer token.** Anyone with the URL sees the ledger. That is the
   intent, but it means the token should never end up in a public place. It is rate limited
   and `noindex`, and the component refuses every write when mounted read-only.
5. **Exchange rates.** The schema and the conversion path are there; there is no rate-fetching
   job yet. Add one against your provider of choice and write into `exchange_rates`.

---

## Things worth building next

Roughly in the order I would do them, based on what the established tools do well:

- **Statement import.** CSV and bank PDF parsing, with the `import_hash` column already in
  place to make re-imports idempotent. The single biggest reduction in manual entry.
- **Receipt OCR** on upload to prefill amount, merchant and date.
- **Net worth over time** — you have accounts, balances and a `exclude_from_net_worth` flag;
  the missing piece is a monthly snapshot table.
- **Lending ledger** for money lent outside any group, with reminders.
- **A month-end review screen**: what changed, what recurring costs went up, what to cancel.
- **Push reminders** for settle-ups that have been sitting unpaid, because the thing that
  kills a shared ledger is nobody ever closing it out.
