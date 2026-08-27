<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Collapse the old Family/Couple/Friends distinction into a single
        // "Group" value before tightening the constraint, so existing rows
        // don't violate it.
        DB::table('trips')->where('travel_type', '!=', 'Solo')->update(['travel_type' => 'Group']);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_travel_type_check');
            DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_travel_type_check CHECK (travel_type IN ('Solo','Group'))");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_travel_type_check');
            DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_travel_type_check CHECK (travel_type IN ('Solo','Family','Couple','Friends'))");
        }
    }
};
