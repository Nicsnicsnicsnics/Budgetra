<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            $table->boolean('pending_profile_offer')->default(false)->after('ai_gen_count');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            $table->dropColumn('pending_profile_offer');
        });
    }
};
