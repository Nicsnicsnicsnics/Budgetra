<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The picker is down to two palettes: "original" (the classic Budgetra
     * brown and cream, now the default for new accounts) and "nightflight".
     * Everyone on a retired palette — and everyone on "auto", which resolved
     * to the now-removed "daylight" in light mode — lands on "original".
     */
    public function up(): void
    {
        DB::table('users')->whereNotIn('theme', ['original', 'nightflight'])
            ->update(['theme' => 'original']);

        $this->setEnum(['original', 'nightflight'], 'original');
    }

    public function down(): void
    {
        $this->setEnum(
            ['daylight', 'nightflight', 'terracotta', 'retro-wanderlust', 'sakura-bloom', 'original', 'auto'],
            'daylight'
        );
    }

    /**
     * $table->enum(...)->change() is the portable path and is what the rest of
     * this table's history uses, but on Postgres it emits an
     * "alter column ... type varchar(255) check (...)" statement the server
     * rejects outright. Postgres stores an enum column as a varchar plus a
     * named CHECK constraint, so there the swap is drop-constraint/re-add.
     */
    private function setEnum(array $values, string $default): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn ($v) => "'".$v."'::character varying", $values));

            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_theme_check');
            DB::statement("ALTER TABLE users ALTER COLUMN theme SET DEFAULT '{$default}'");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_theme_check CHECK (theme::text = ANY (ARRAY[{$list}]::text[]))");

            return;
        }

        Schema::table('users', function (Blueprint $table) use ($values, $default) {
            $table->enum('theme', $values)->default($default)->change();
        });
    }
};
