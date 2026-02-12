<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add block_id to ta_blocks and apartment_id to ta_apartments if missing (old server schema).
     */
    public function up(): void
    {
        if (Schema::hasTable('ta_blocks') && ! Schema::hasColumn('ta_blocks', 'block_id')) {
            Schema::table('ta_blocks', function (Blueprint $table) {
                $table->string('block_id', 64)->nullable()->first();
            });
        }
        if (Schema::hasTable('ta_blocks') && ! Schema::hasColumn('ta_blocks', 'city_id')) {
            Schema::table('ta_blocks', function (Blueprint $table) {
                $table->string('city_id', 64)->nullable();
            });
        }

        if (Schema::hasTable('ta_apartments') && ! Schema::hasColumn('ta_apartments', 'apartment_id')) {
            Schema::table('ta_apartments', function (Blueprint $table) {
                $table->string('apartment_id', 64)->nullable()->first();
            });
        }
    }

    public function down(): void
    {
        // No down: do not drop business key columns
    }
};
