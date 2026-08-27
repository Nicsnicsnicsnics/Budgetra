<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('moment_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moment_id')->constrained()->cascadeOnDelete();
            $table->string('photo_path');
            $table->timestamps();
        });

        // Carry forward any photo already stored under the old single-photo
        // column before dropping it, so existing pins don't lose their photo.
        DB::table('moments')->whereNotNull('photo_path')->get()->each(function ($moment) {
            DB::table('moment_photos')->insert([
                'moment_id'  => $moment->id,
                'photo_path' => $moment->photo_path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('moments', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
        });
        Schema::dropIfExists('moment_photos');
    }
};
