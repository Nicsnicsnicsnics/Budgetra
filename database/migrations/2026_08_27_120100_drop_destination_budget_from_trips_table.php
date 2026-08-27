<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * trips.destination_budget was written on every trip save (a peso figure divided
 * by whatever rate was live at that moment) and never read back anywhere in the
 * app. It also went NULL whenever the rate lookup failed, so it was not even a
 * dependable snapshot. Trip cards convert on demand from destination_currency,
 * which stays correct as rates move.
 *
 * DESTRUCTIVE: this drops a column. Nothing reads it, but the stored values are
 * gone once this runs. down() restores the column, not its contents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('destination_budget');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('destination_budget', 15, 2)->nullable()->after('destination_currency');
        });
    }
};
