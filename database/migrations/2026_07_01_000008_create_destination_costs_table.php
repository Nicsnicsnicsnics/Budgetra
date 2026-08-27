<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('destination_costs', function (Blueprint $table) {
            $table->id();
            $table->string('destination');
            $table->string('category', 100)->nullable();
            $table->enum('cost_level', ['Budget-friendly', 'Moderate', 'Pricey', 'Very Expensive'])->default('Moderate');
            $table->decimal('multiplier', 4, 3)->default(1.000);
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('destination_costs'); }
};
