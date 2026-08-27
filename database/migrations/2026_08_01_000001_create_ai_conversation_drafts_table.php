<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_conversation_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('messages');
            $table->string('ai_from')->default('');
            $table->string('ai_to')->default('');
            $table->integer('ai_budget_min')->default(0);
            $table->integer('ai_budget_max')->default(0);
            $table->string('ai_date_from')->default('');
            $table->string('ai_date_to')->default('');
            $table->integer('ai_days')->default(0);
            $table->string('awaiting_slot')->default('');
            $table->integer('miss_count')->default(0);
            $table->string('ai_step')->default('');
            $table->json('ai_package')->nullable();
            $table->integer('ai_gen_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_drafts');
    }
};
