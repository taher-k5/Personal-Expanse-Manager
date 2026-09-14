<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null category = a single overall budget for the month.
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('INR');
            // monthly | weekly | yearly
            $table->string('period')->default('monthly');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            // Envelope behaviour: unspent money carries into next period.
            $table->boolean('rollover')->default(false);
            $table->bigInteger('rollover_balance_minor')->default(0);
            // Notify at this share of the budget, e.g. 0.80.
            $table->decimal('alert_threshold', 4, 2)->default(0.80);
            $table->timestamp('alerted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->bigInteger('target_minor');
            $table->bigInteger('saved_minor')->default(0);
            $table->char('currency', 3)->default('INR');
            $table->date('target_date')->nullable();
            $table->string('colour', 9)->default('#0E8A5F');
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
        Schema::dropIfExists('budgets');
    }
};
