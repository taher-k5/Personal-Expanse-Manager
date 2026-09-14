<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rent, salary, Netflix. A scheduled command materialises these into transactions.
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('expense');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('INR');
            // daily | weekly | fortnightly | monthly | quarterly | yearly
            $table->string('frequency')->default('monthly');
            $table->unsignedSmallInteger('interval')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_run_on');
            $table->date('last_run_on')->nullable();
            // Post automatically, or just remind the user to confirm it.
            $table->boolean('auto_post')->default(true);
            $table->unsignedTinyInteger('remind_days_before')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'next_run_on']);
        });

        // "If the merchant looks like X, file it under Y." Learned from the user's own edits.
        Schema::create('categorisation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('match_type')->default('contains'); // contains | starts_with | regex
            $table->string('pattern');
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('times_applied')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'priority']);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base', 3);
            $table->char('quote', 3);
            $table->decimal('rate', 18, 8);
            $table->date('rate_on');
            $table->timestamps();

            $table->unique(['base', 'quote', 'rate_on']);
        });

        // Added here, rather than on the transactions migration, because that one runs
        // before this table exists.
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('recurring_transaction_id')->references('id')->on('recurring_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['recurring_transaction_id']);
        });

        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('categorisation_rules');
        Schema::dropIfExists('recurring_transactions');
    }
};
