<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expenses had a single `amount` that the whole app read as pesos, so a traveller
 * spending yen in Japan had to hand-convert every purchase — and a scanned ¥3,500
 * receipt was recorded as ₱3,500, overstating the trip by ~2.6x.
 *
 * `amount` keeps its meaning: it is still the peso figure ExpenseObserver, trip
 * totals and Multi-Trip comparisons read, so nothing downstream changes. The two
 * new columns record what was actually spent, mirroring the shape already used by
 * user_profiles.daily_budget / daily_budget_local / daily_budget_currency.
 *
 * Defaulting amount_currency to PHP is correct for every row written to date —
 * they were all peso entries — so no backfill is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('amount_currency', 3)->default('PHP')->after('amount');
            $table->decimal('amount_original', 15, 2)->nullable()->after('amount_currency');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['amount_currency', 'amount_original']);
        });
    }
};
