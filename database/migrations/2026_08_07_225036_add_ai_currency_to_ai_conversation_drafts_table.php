<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            $table->string('ai_currency', 3)->default('PHP')->after('ai_travelers');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversation_drafts', function (Blueprint $table) {
            $table->dropColumn('ai_currency');
        });
    }
};
