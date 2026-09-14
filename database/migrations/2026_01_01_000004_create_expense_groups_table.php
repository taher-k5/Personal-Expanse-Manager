<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A group is a trip, an event, a shared flat, or an ongoing "me and Priya" ledger.
        // Named expense_groups because GROUPS is a reserved word in MySQL 8.
        Schema::create('expense_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            // trip | event | household | project | other
            $table->string('type')->default('trip');
            $table->text('description')->nullable();
            $table->char('currency', 3)->default('INR');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('destination')->nullable();
            $table->string('cover_path')->nullable();
            // Public read-only link so people without an account can see the ledger.
            $table->string('share_token', 40)->unique()->nullable();
            $table->boolean('share_enabled')->default(false);
            $table->boolean('simplify_debts')->default(true);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'type']);
        });

        // Members can be real users OR bare name/email placeholders — "share with anyone".
        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('upi_vpa')->nullable();
            // owner | editor | viewer
            $table->string('role')->default('editor');
            // Default weight when the group splits by shares (a couple sharing a room = 2).
            $table->unsignedInteger('default_shares')->default(1);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['expense_group_id', 'user_id']);
            $table->index('expense_group_id');
        });

        Schema::create('group_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_invites');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('expense_groups');
    }
};
