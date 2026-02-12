<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make external_id nullable on ta_blocks/ta_apartments if present (server may have old schema).
     * Fixes: Field 'external_id' doesn't have a default value.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        if (Schema::hasTable('ta_blocks') && Schema::hasColumn('ta_blocks', 'external_id')) {
            DB::statement('ALTER TABLE ta_blocks MODIFY COLUMN external_id VARCHAR(100) NULL');
        }

        if (Schema::hasTable('ta_apartments') && Schema::hasColumn('ta_apartments', 'external_id')) {
            DB::statement('ALTER TABLE ta_apartments MODIFY COLUMN external_id VARCHAR(100) NULL');
        }
    }

    public function down(): void
    {
        // No down: nullable is desired for inserts without external_id
    }
};
