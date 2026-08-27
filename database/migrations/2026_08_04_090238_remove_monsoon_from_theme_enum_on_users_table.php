<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("UPDATE users SET theme = 'daylight' WHERE theme = 'monsoon'");
        Schema::table('users', function (Blueprint $table) {
            $table->enum('theme', ['daylight', 'nightflight', 'terracotta', 'retro-wanderlust', 'sakura-bloom', 'auto'])
                ->default('daylight')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('theme', ['daylight', 'nightflight', 'terracotta', 'monsoon', 'retro-wanderlust', 'sakura-bloom', 'auto'])
                ->default('daylight')->change();
        });
    }
};
