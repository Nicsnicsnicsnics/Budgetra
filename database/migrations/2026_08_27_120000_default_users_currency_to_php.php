<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * users.currency_code/currency_symbol defaulted to USD/'$' and registration never
 * set them, so every new account was USD. Nothing in the app converted anything
 * for that setting — it only relabelled peso figures, including the expense and
 * savings amount inputs — so the default quietly mislabelled the ledger.
 *
 * The columns are kept rather than dropped: they are referenced by existing rows
 * and by Settings history, and dropping a column on a live Postgres table is a
 * heavier, harder-to-reverse change than neutralising it. app/helpers.php no
 * longer reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill every account onto the peso ledger the app actually uses.
        DB::table('users')
            ->where('currency_code', '!=', 'PHP')
            ->orWhereNull('currency_code')
            ->update(['currency_code' => 'PHP', 'currency_symbol' => '₱']);

        // Raw DDL rather than doctrine/dbal change(): this only alters the
        // default and avoids re-declaring the column type. SQLite (used by the
        // test suite) has no ALTER COLUMN and would abort the migration run —
        // the backfill above is the part that matters there anyway.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN currency_code SET DEFAULT 'PHP'");
            DB::statement("ALTER TABLE users ALTER COLUMN currency_symbol SET DEFAULT '₱'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN currency_code SET DEFAULT 'USD'");
            DB::statement("ALTER TABLE users ALTER COLUMN currency_symbol SET DEFAULT '$'");
        }
    }
};
