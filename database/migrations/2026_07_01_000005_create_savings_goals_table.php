<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('goal_name');
            $table->decimal('target_amount', 10, 2);
            $table->decimal('current_savings', 10, 2)->default(0);
            $table->date('deadline');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('savings_goals'); }
};
