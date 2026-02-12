<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make hash nullable on ta_blocks/ta_apartments if present (server legacy schema).
     * Fixes: Field 'hash' doesn't have a default value.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        if (Schema::hasTable('ta_blocks') && Schema::hasColumn('ta_blocks', 'hash')) {
            DB::statement('ALTER TABLE ta_blocks MODIFY COLUMN hash VARCHAR(64) NULL');
        }

        if (Schema::hasTable('ta_apartments') && Schema::hasColumn('ta_apartments', 'hash')) {
            DB::statement('ALTER TABLE ta_apartments MODIFY COLUMN hash VARCHAR(64) NULL');
        }
    }

    public function down(): void
    {
        // No down: nullable is desired
    }
};
