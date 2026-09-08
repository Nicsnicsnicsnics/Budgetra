<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The conversational planner had the same bug the wizard fixed in
 * 2026_08_27_130100: a bare budget was stored raw into the peso column and
 * merely labelled with the traveller's currency, so ¥50,000 became ₱50,000.
 *
 * Converting on entry is only half of it — trips.budget_local wants the figure
 * the traveller actually typed, and the budget is answered several turns before
 * the trip row is written. ai_budget_min/max are the converted pesos, so this
 * carries the original across those turns to be written alongside them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            $table->decimal('ai_budget_local', 15, 2)->nullable()->after('ai_budget_max');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            $table->dropColumn('ai_budget_local');
        });
    }
};
