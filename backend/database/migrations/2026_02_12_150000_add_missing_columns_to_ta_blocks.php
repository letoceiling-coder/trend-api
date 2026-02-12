<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to ta_blocks (table may have been created by older migration).
     */
    public function up(): void
    {
        if (! Schema::hasTable('ta_blocks')) {
            return;
        }

        Schema::table('ta_blocks', function (Blueprint $table) {
            if (! Schema::hasColumn('ta_blocks', 'lang')) {
                $table->string('lang', 8)->default('ru')->after('city_id');
            }
            if (! Schema::hasColumn('ta_blocks', 'guid')) {
                $table->string('guid', 100)->nullable()->after('block_id');
            }
            if (! Schema::hasColumn('ta_blocks', 'title')) {
                $table->string('title')->nullable()->after('guid');
            }
            if (! Schema::hasColumn('ta_blocks', 'kind')) {
                $table->string('kind', 50)->nullable()->after('lang');
            }
            if (! Schema::hasColumn('ta_blocks', 'status')) {
                $table->string('status', 50)->nullable()->after('kind');
            }
            if (! Schema::hasColumn('ta_blocks', 'min_price')) {
                $table->bigInteger('min_price')->nullable()->after('status');
            }
            if (! Schema::hasColumn('ta_blocks', 'max_price')) {
                $table->bigInteger('max_price')->nullable()->after('min_price');
            }
            if (! Schema::hasColumn('ta_blocks', 'deadline')) {
                $table->string('deadline', 100)->nullable()->after('max_price');
            }
            if (! Schema::hasColumn('ta_blocks', 'developer_name')) {
                $table->string('developer_name')->nullable()->after('deadline');
            }
            if (! Schema::hasColumn('ta_blocks', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('developer_name');
            }
            if (! Schema::hasColumn('ta_blocks', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
            if (! Schema::hasColumn('ta_blocks', 'raw')) {
                $table->longText('raw')->nullable()->after('lng');
            }
            if (! Schema::hasColumn('ta_blocks', 'fetched_at')) {
                $table->timestamp('fetched_at')->nullable()->after('raw');
            }
            if (! Schema::hasColumn('ta_blocks', 'normalized')) {
                $table->json('normalized')->nullable()->after('raw');
            }
            if (! Schema::hasColumn('ta_blocks', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable()->after('normalized');
            }
        });
    }

    public function down(): void
    {
        // No down: we don't drop columns that may contain data
    }
};
