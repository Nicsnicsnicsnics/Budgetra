<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops users.currency_code and users.currency_symbol.
 *
 * They were an account-level display currency that never converted anything —
 * it only relabelled peso figures, including the expense and savings amount
 * inputs, so a traveller typing 100 for a $100 dinner stored ₱100. The
 * 2026_08_27 migration neutralised them by backfilling every row to PHP and
 * left the columns in place; app/helpers.php has returned a hardcoded 'PHP'
 * and '₱' ever since, and nothing reads the columns at all.
 *
 * What was left was a row reading "United States | PHP", which reads like a
 * bug and is not one: a traveller's own currency is home_currency(), derived
 * from users.country at read time, and that has always answered USD there.
 * The columns are removed so nothing can misread them again — and so nothing
 * can start reading them and bring the original mislabelling back.
 *
 * Reversible: down() restores both columns on the peso default the app
 * settled on, not the USD default they were created with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Guarded individually: an environment part-way through this
            // history should not abort on the column it already lacks.
            if (Schema::hasColumn('users', 'currency_code')) {
                $table->dropColumn('currency_code');
            }
            if (Schema::hasColumn('users', 'currency_symbol')) {
                $table->dropColumn('currency_symbol');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'currency_code')) {
                $table->string('currency_code', 10)->default('PHP');
            }
            if (! Schema::hasColumn('users', 'currency_symbol')) {
                $table->string('currency_symbol', 10)->default('₱');
            }
        });
    }
};
