<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('place_name');
            $table->text('description')->nullable();
            $table->date('visited_date');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('moments'); }
};
