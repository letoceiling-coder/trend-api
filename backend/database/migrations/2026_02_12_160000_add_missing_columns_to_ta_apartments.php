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
            if (! Schema::hasColumn('ta_apartments', 'block_id')) {
                $table->string('block_id', 64)->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'guid')) {
                $table->string('guid', 255)->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'title')) {
                $table->string('title', 512)->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'rooms')) {
                $table->unsignedTinyInteger('rooms')->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'area_total')) {
                $table->decimal('area_total', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'floor')) {
                $table->unsignedSmallInteger('floor')->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'price')) {
                $table->unsignedBigInteger('price')->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'status')) {
                $table->string('status', 64)->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'city_id')) {
                $table->string('city_id', 64)->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'lang')) {
                $table->string('lang', 8)->default('ru');
            }
            if (! Schema::hasColumn('ta_apartments', 'raw')) {
                $table->json('raw')->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'normalized')) {
                $table->json('normalized')->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('ta_apartments', 'fetched_at')) {
                $table->timestamp('fetched_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No down: we don't drop columns that may contain data
    }
};
