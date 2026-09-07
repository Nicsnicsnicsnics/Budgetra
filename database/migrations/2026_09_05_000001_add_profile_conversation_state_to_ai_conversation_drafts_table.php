<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            // The AI planner can now collect a travel profile conversationally,
            // which is a second, parallel slot-filling flow to the trip one.
            // Its state rides along in the same draft row so closing the tab
            // mid-profile resumes exactly like closing it mid-trip already does.
            $table->boolean('building_profile')->default(false)->after('pending_profile_offer');
            $table->json('profile_draft')->nullable()->after('building_profile');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            $table->dropColumn(['building_profile', 'profile_draft']);
        });
    }
};
