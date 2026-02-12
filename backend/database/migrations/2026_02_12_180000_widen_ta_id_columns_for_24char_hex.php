<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen city_id / block_id / apartment_id to VARCHAR(64) so 24-char hex IDs fit.
     * Fixes: SQLSTATE[01000]: Warning: 1265 Data truncated for column 'city_id' at row 1
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        if (Schema::hasTable('ta_blocks')) {
            if (Schema::hasColumn('ta_blocks', 'block_id')) {
                DB::statement('ALTER TABLE ta_blocks MODIFY COLUMN block_id VARCHAR(64) NULL');
            }
            if (Schema::hasColumn('ta_blocks', 'city_id')) {
                DB::statement('ALTER TABLE ta_blocks MODIFY COLUMN city_id VARCHAR(64) NULL');
            }
        }

        if (Schema::hasTable('ta_apartments')) {
            if (Schema::hasColumn('ta_apartments', 'apartment_id')) {
                DB::statement('ALTER TABLE ta_apartments MODIFY COLUMN apartment_id VARCHAR(64) NULL');
            }
            if (Schema::hasColumn('ta_apartments', 'block_id')) {
                DB::statement('ALTER TABLE ta_apartments MODIFY COLUMN block_id VARCHAR(64) NULL');
            }
            if (Schema::hasColumn('ta_apartments', 'city_id')) {
                DB::statement('ALTER TABLE ta_apartments MODIFY COLUMN city_id VARCHAR(64) NULL');
            }
        }
    }

    public function down(): void
    {
        // No down: widening is safe and should not be reverted
    }
};
