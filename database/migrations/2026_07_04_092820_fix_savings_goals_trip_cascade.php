<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Delete orphaned savings goals (trip was deleted, trip_id became null)
        DB::table('savings_goals')->whereNull('trip_id')->delete();

        Schema::table('savings_goals', function (Blueprint $table) {
            // Drop the old nullable foreign key
            $table->dropForeign(['trip_id']);
            // Re-add as non-nullable with cascade
            $table->foreignId('trip_id')->nullable(false)->change();
            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('savings_goals', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
            $table->foreignId('trip_id')->nullable()->change();
            $table->foreign('trip_id')->references('id')->on('trips')->nullOnDelete();
        });
    }
};
