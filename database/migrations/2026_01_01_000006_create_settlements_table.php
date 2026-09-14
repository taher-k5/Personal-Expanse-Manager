<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A settlement is money moving between two people to clear a balance.
        // It is deliberately separate from transactions: it is not spending.
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('from_member_id')->constrained('group_members')->cascadeOnDelete();
            $table->foreignId('to_member_id')->constrained('group_members')->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('INR');
            $table->date('settled_on');
            // upi | cash | bank_transfer | card | other
            $table->string('method')->default('upi');
            // pending | awaiting_confirmation | confirmed | cancelled
            $table->string('status')->default('confirmed');
            // Reference we put in the UPI intent so a human can match it in their bank app.
            $table->string('payment_reference')->nullable();
            $table->string('upi_intent_uri', 1000)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['expense_group_id', 'settled_on']);
            $table->index(['from_member_id', 'to_member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
