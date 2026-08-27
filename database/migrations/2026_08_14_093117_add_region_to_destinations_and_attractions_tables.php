<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Keywords covering every Philippine city/province currently seeded or
    // referenced by real destination/attraction rows — used only to
    // backfill the new `region` column's initial value for existing rows;
    // going forward the admin panel sets it explicitly per entry.
    private const PH_KEYWORDS = [
        'batanes', 'batangas', 'bohol', 'boracay', 'camiguin', 'cebu', 'coron',
        'davao', 'dumaguete', 'el nido', 'siargao', 'iloilo', 'la union',
        'legazpi', 'malapascua', 'naga', 'pagudpud', 'puerto princesa',
        'sagada', 'tagaytay', 'vigan', 'zamboanga', 'bacolod', 'baguio',
        'cagayan de oro', 'general santos', 'general luna', 'laoag', 'manila',
        'siquijor', 'surigao', 'tacloban', 'tagbilaran', 'palawan', 'bicol',
        'ilocos', 'mindanao', 'luzon', 'visayas', 'philippines',
    ];

    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->string('region')->nullable()->after('country');
        });
        Schema::table('attractions', function (Blueprint $table) {
            $table->string('region')->nullable()->after('category');
        });

        DB::table('destinations')->where('country', 'Philippines')->update(['region' => 'local']);
        DB::table('destinations')->where(function ($q) {
            $q->where('country', '!=', 'Philippines')->orWhereNull('country');
        })->update(['region' => 'international']);

        DB::table('attractions')->where(function ($q) {
            foreach (self::PH_KEYWORDS as $keyword) {
                $q->orWhereRaw('LOWER(destination) LIKE ?', ['%' . $keyword . '%']);
            }
        })->update(['region' => 'local']);
        DB::table('attractions')->whereNull('region')->update(['region' => 'international']);
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn('region');
        });
        Schema::table('attractions', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
