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
        Schema::table('trips', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->after('notes');
            $table->decimal('total_cost', 10, 2)->nullable()->after('cover_image');
            $table->string('origin')->nullable()->after('destination');
            $table->string('origin_code')->nullable()->after('origin');
            $table->string('destination_code')->nullable()->after('origin_code');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['cover_image', 'total_cost', 'origin', 'origin_code', 'destination_code']);
        });
    }
};
