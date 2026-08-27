<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('serpapi_cache', function (Blueprint $table) {
            $table->id();
            $table->char('query_hash', 64)->unique();
            $table->text('query_params');
            $table->longText('response_json');
            $table->dateTime('created_at');
            $table->dateTime('expires_at')->index();
        });

        Schema::create('serpapi_usage', function (Blueprint $table) {
            $table->date('usage_date')->primary();
            $table->integer('request_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serpapi_cache');
        Schema::dropIfExists('serpapi_usage');
    }
};
