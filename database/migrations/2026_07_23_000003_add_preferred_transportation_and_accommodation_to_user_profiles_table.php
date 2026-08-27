<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('preferred_transportation')->nullable()->after('sub_interests');
            $table->string('preferred_accommodation')->nullable()->after('preferred_transportation');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['preferred_transportation', 'preferred_accommodation']);
        });
    }
};
