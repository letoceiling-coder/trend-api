<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to ta_apartments (table may have been created by older migration).
     */
    public function up(): void
    {
        if (! Schema::hasTable('ta_apartments')) {
            return;
        }

        Schema::table('ta_apartments', function (Blueprint $table) {
            if (! Schema::hasColumn('ta_apartments', 'lang')) {
                $table->string('lang', 8)->default('ru')->after('city_id');
            }
            if (! Schema::hasColumn('ta_apartments', 'guid')) {
                $table->string('guid', 255)->nullable()->after('block_id');
            }
            if (! Schema::hasColumn('ta_apartments', 'title')) {
                $table->string('title', 512)->nullable()->after('guid');
            }
            if (! Schema::hasColumn('ta_apartments', 'rooms')) {
                $table->unsignedTinyInteger('rooms')->nullable()->after('title');
            }
            if (! Schema::hasColumn('ta_apartments', 'area_total')) {
                $table->decimal('area_total', 10, 2)->nullable()->after('rooms');
            }
            if (! Schema::hasColumn('ta_apartments', 'floor')) {
                $table->unsignedSmallInteger('floor')->nullable()->after('area_total');
            }
            if (! Schema::hasColumn('ta_apartments', 'price')) {
                $table->unsignedBigInteger('price')->nullable()->after('floor');
            }
            if (! Schema::hasColumn('ta_apartments', 'status')) {
                $table->string('status', 64)->nullable()->after('price');
            }
            if (! Schema::hasColumn('ta_apartments', 'raw')) {
                $table->json('raw')->nullable()->after('lang');
            }
            if (! Schema::hasColumn('ta_apartments', 'normalized')) {
                $table->json('normalized')->nullable()->after('raw');
            }
            if (! Schema::hasColumn('ta_apartments', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable()->after('normalized');
            }
            if (! Schema::hasColumn('ta_apartments', 'fetched_at')) {
                $table->timestamp('fetched_at')->nullable()->after('payload_hash');
            }
        });
    }

    public function down(): void
    {
        // No down: we don't drop columns that may contain data
    }
};
