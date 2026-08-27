<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->enum('category', [
                'Transportation', 'Accommodation', 'Food',
                'Tourist Attractions', 'Shopping', 'Emergency Funds',
            ]);
            $table->decimal('estimated_cost', 10, 2)->default(0);
            $table->decimal('actual_spent', 10, 2)->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('trip_budgets'); }
};
