<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_conversation_histories', function (Blueprint $table) {
            $table->id();
            // Not unique, unlike ai_conversation_drafts — a user can have
            // any number of past conversations archived here.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('messages');
            $table->string('ai_from')->default('');
            $table->string('ai_to')->default('');
            $table->integer('ai_budget_min')->default(0);
            $table->integer('ai_budget_max')->default(0);
            $table->string('ai_date_from')->default('');
            $table->string('ai_date_to')->default('');
            $table->integer('ai_days')->default(0);
            $table->json('ai_package')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_histories');
    }
};
