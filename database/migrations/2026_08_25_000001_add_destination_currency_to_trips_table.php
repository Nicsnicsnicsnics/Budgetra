<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('destination_currency', 3)->nullable()->after('budget_limit');
            $table->decimal('destination_budget', 15, 2)->nullable()->after('destination_currency');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['destination_currency', 'destination_budget']);
        });
    }
};
