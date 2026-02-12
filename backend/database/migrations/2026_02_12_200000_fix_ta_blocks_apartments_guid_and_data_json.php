<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1) Widen guid so long slugs fit (e.g. filosofiya-idealistov-na-pervomayskoy).
     * 2) Make data_json nullable on ta_blocks/ta_apartments if present (server legacy schema).
     * Fixes: Data too long for column 'guid', Field 'data_json' doesn't have a default value.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        if (Schema::hasTable('ta_blocks')) {
            if (Schema::hasColumn('ta_blocks', 'guid')) {
                DB::statement('ALTER TABLE ta_blocks MODIFY COLUMN guid VARCHAR(512) NULL');
            }
            if (Schema::hasColumn('ta_blocks', 'data_json')) {
                DB::statement('ALTER TABLE ta_blocks MODIFY COLUMN data_json JSON NULL');
            }
        }

        if (Schema::hasTable('ta_apartments')) {
            if (Schema::hasColumn('ta_apartments', 'guid')) {
                DB::statement('ALTER TABLE ta_apartments MODIFY COLUMN guid VARCHAR(512) NULL');
            }
            if (Schema::hasColumn('ta_apartments', 'data_json')) {
                DB::statement('ALTER TABLE ta_apartments MODIFY COLUMN data_json JSON NULL');
            }
        }
    }

    public function down(): void
    {
        // No down: wider guid and nullable data_json are safe
    }
};
