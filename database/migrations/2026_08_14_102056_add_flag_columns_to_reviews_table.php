<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('flag_reason')->nullable()->after('status');
            $table->timestamp('flagged_at')->nullable()->after('flag_reason');
            $table->foreignId('flagged_by')->nullable()->after('flagged_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flagged_by');
            $table->dropColumn(['flag_reason', 'flagged_at']);
        });
    }
};
