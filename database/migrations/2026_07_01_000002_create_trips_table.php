<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('destination');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('num_travelers')->default(1);
            $table->decimal('budget_limit', 10, 2)->nullable();
            $table->enum('travel_type', ['Solo', 'Family', 'Couple', 'Friends']);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('trips'); }
};
