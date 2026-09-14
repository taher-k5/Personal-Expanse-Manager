<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('base_currency', 3)->default('INR')->after('email');
            $table->string('timezone')->default('Asia/Kolkata')->after('base_currency');
            // Used to generate UPI / Google Pay collect links when friends settle up.
            $table->string('upi_vpa')->nullable()->after('timezone');
            $table->string('phone')->nullable()->after('upi_vpa');
            $table->unsignedTinyInteger('month_starts_on')->default(1)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['base_currency', 'timezone', 'upi_vpa', 'phone', 'month_starts_on']);
        });
    }
};
