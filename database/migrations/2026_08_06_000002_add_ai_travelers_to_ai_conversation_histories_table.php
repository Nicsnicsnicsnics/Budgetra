<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_conversation_histories', function (Blueprint $table) {
            $table->integer('ai_travelers')->default(0)->after('ai_days');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversation_histories', function (Blueprint $table) {
            $table->dropColumn('ai_travelers');
        });
    }
};
