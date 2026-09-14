<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // cash | bank | card | wallet | credit_card | investment | loan
            $table->string('type')->default('bank');
            $table->char('currency', 3)->default('INR');
            $table->bigInteger('opening_balance_minor')->default(0);
            // Denormalised running balance, recalculated by Account::recalculateBalance().
            $table->bigInteger('current_balance_minor')->default(0);
            $table->bigInteger('credit_limit_minor')->nullable();
            $table->unsignedTinyInteger('statement_day')->nullable();
            $table->string('colour', 9)->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->boolean('exclude_from_net_worth')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
