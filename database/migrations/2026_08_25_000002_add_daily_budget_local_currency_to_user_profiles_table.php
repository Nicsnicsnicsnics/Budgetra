<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('daily_budget_currency', 3)->nullable()->after('daily_budget');
            $table->decimal('daily_budget_local', 15, 2)->nullable()->after('daily_budget_currency');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['daily_budget_currency', 'daily_budget_local']);
        });
    }
};
