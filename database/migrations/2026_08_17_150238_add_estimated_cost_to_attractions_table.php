<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            // Typical per-person cost to visit — the anchor reviews' actual
            // spent_amount is compared against for the cost-accuracy summary.
            $table->decimal('estimated_cost', 10, 2)->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('attractions', function (Blueprint $table) {
            $table->dropColumn('estimated_cost');
        });
    }
};
