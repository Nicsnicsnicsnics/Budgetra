<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            $table->decimal('emergency_fund', 10, 2)->default(0)->after('ai_gen_count');
            $table->json('ai_itinerary_options')->nullable()->after('emergency_fund');
            $table->json('ai_itinerary')->nullable()->after('ai_itinerary_options');
            $table->integer('selected_itinerary_index')->default(0)->after('ai_itinerary');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            $table->dropColumn(['emergency_fund', 'ai_itinerary_options', 'ai_itinerary', 'selected_itinerary_index']);
        });
    }
};
