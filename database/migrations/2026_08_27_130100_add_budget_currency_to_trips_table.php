<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wizard's budget field was unlabelled and silently meant pesos, so a Canadian
 * typing 3,000 for CAD 3,000 got a ₱3,000 trip — about 1/41 of what they intended.
 *
 * budget_limit stays the peso figure everything else reads. These record what the
 * traveller actually typed, so reopening a trip shows their own number back rather
 * than a peso figure re-divided by whatever rate is live that day (which would both
 * drift and risk double-converting on re-save).
 *
 * They also give trips a persistent currency: tripCurrency previously existed only
 * in memory, set by the AI handoff, and was lost the moment the wizard closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('budget_currency', 3)->nullable()->after('budget_limit');
            $table->decimal('budget_local', 15, 2)->nullable()->after('budget_currency');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['budget_currency', 'budget_local']);
        });
    }
};
