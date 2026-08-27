<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->json('flight_selection')->nullable()->after('summary_data');
            $table->json('hotel_selection')->nullable()->after('flight_selection');
            $table->json('venue_selection')->nullable()->after('hotel_selection');
            $table->json('attraction_selection')->nullable()->after('venue_selection');
            $table->json('leg2_flight_selection')->nullable()->after('attraction_selection');
            $table->json('leg2_hotel_selection')->nullable()->after('leg2_flight_selection');
            $table->json('leg2_venue_selection')->nullable()->after('leg2_hotel_selection');
            $table->json('leg2_attraction_selection')->nullable()->after('leg2_venue_selection');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'flight_selection', 'hotel_selection', 'venue_selection', 'attraction_selection',
                'leg2_flight_selection', 'leg2_hotel_selection', 'leg2_venue_selection', 'leg2_attraction_selection',
            ]);
        });
    }
};
