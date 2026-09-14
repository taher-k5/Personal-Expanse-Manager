<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Shared spending: which trip/event this belongs to, and who actually paid.
            $table->foreignId('expense_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paid_by_member_id')->nullable()->constrained('group_members')->nullOnDelete();

            // expense | income | transfer
            $table->string('type')->default('expense');

            // Money is always stored as an integer in the smallest unit. Never floats.
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('INR');
            // For foreign spending: the value in the user's base currency at time of entry.
            $table->bigInteger('base_amount_minor')->nullable();
            $table->decimal('exchange_rate', 18, 8)->nullable();

            $table->date('booked_on');
            $table->string('merchant')->nullable();
            $table->string('normalised_merchant')->nullable();
            $table->text('note')->nullable();
            $table->string('receipt_path')->nullable();

            // Transfers point at the destination account; both legs share transfer_group_uuid.
            $table->foreignId('transfer_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->uuid('transfer_group_uuid')->nullable();

            // equal | shares | percentage | exact | adjustment | none
            $table->string('split_method')->default('none');

            // FK added in the recurring_transactions migration, which runs after this one.
            $table->foreignId('recurring_transaction_id')->nullable();
            $table->boolean('is_reimbursable')->default(false);
            $table->boolean('exclude_from_budget')->default(false);
            $table->string('import_hash')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'booked_on']);
            $table->index(['user_id', 'category_id', 'booked_on']);
            $table->index(['expense_group_id', 'booked_on']);
            $table->index(['user_id', 'normalised_merchant']);
            $table->unique(['user_id', 'import_hash']);
        });

        // One row per person who owes a piece of a shared transaction.
        Schema::create('transaction_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_member_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            // The raw input that produced amount_minor: a weight, a percent, or a fixed value.
            $table->decimal('split_input', 18, 6)->nullable();
            $table->timestamps();

            $table->unique(['transaction_id', 'group_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_splits');
        Schema::dropIfExists('transactions');
    }
};
