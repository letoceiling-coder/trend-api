<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ta_payload_cache')) {
            return;
        }
        Schema::create('ta_payload_cache', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('trendagent');
            $table->string('scope', 100); // 'directories', 'unit_measurements', 'blocks', etc.
            $table->string('external_id', 100)->nullable(); // e.g. block_id, directory type
            $table->string('city_id', 50)->nullable();
            $table->string('lang', 10)->nullable();
            $table->string('etag', 100)->nullable();
            $table->longText('payload'); // Full JSON response
            $table->timestamp('fetched_at');
            $table->timestamps();

            // SQLite: full composite. MySQL: prefix index for key length limit.
            if (Schema::getConnection()->getDriverName() !== 'mysql') {
                $table->index(['provider', 'scope', 'external_id', 'city_id', 'lang']);
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX ta_payload_cache_provider_scope_ext_city_lang_index ON ta_payload_cache (provider(50), scope(50), external_id(50), city_id(50), lang(10))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_payload_cache');
    }
};
