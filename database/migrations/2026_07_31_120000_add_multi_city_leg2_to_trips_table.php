<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('is_multi_city')->default(false)->after('destination_code');
            $table->string('leg2_destination')->nullable()->after('is_multi_city');
            $table->string('leg2_destination_code')->nullable()->after('leg2_destination');
            $table->date('leg2_start_date')->nullable()->after('leg2_destination_code');
            $table->date('leg2_end_date')->nullable()->after('leg2_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['is_multi_city', 'leg2_destination', 'leg2_destination_code', 'leg2_start_date', 'leg2_end_date']);
        });
    }
};
