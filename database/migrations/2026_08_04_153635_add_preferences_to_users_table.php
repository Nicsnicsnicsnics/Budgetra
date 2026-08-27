<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('default_buffer_pct')->default(10)->after('theme');
            $table->boolean('notify_budget_alerts')->default(true)->after('default_buffer_pct');
            $table->boolean('notify_trip_reminders')->default(true)->after('notify_budget_alerts');
            $table->boolean('notify_itinerary_reminders')->default(false)->after('notify_trip_reminders');
            $table->boolean('ocr_auto_categorize')->default(true)->after('notify_itinerary_reminders');
            $table->timestamp('password_changed_at')->nullable()->after('ocr_auto_categorize');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'default_buffer_pct', 'notify_budget_alerts', 'notify_trip_reminders',
                'notify_itinerary_reminders', 'ocr_auto_categorize', 'password_changed_at',
            ]);
        });
    }
};
