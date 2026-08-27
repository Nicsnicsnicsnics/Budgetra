<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('trip_type', 20)->nullable()->after('body');
            $table->unsignedSmallInteger('pax_count')->nullable()->after('trip_type');
            $table->decimal('spent_amount', 10, 2)->nullable()->after('pax_count');
            $table->unsignedInteger('helpful_count')->default(0)->after('spent_amount');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['trip_type', 'pax_count', 'spent_amount', 'helpful_count']);
        });
    }
};
