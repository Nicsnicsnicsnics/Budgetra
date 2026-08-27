<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('home_city')->nullable();
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->json('interests')->nullable();      // ['Beach','Nature',...]
            $table->json('sub_interests')->nullable();  // ['Mountains','Hiking',...]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
