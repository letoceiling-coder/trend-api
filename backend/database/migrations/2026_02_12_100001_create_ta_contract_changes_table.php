<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ta_contract_changes')) {
            return;
        }
        Schema::create('ta_contract_changes', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 512)->index();
            $table->string('city_id', 50)->nullable();
            $table->string('lang', 10)->nullable();
            $table->string('old_payload_hash', 64);
            $table->string('new_payload_hash', 64);
            $table->json('old_top_keys')->nullable();
            $table->json('new_top_keys')->nullable();
            $table->json('old_data_keys')->nullable();
            $table->json('new_data_keys')->nullable();
            $table->unsignedBigInteger('payload_cache_id')->nullable();
            $table->timestamp('detected_at');

            if (Schema::getConnection()->getDriverName() !== 'mysql') {
                $table->unique(
                    ['endpoint', 'city_id', 'lang', 'old_payload_hash', 'new_payload_hash'],
                    'ta_contract_changes_endpoint_city_lang_old_new_unique'
                );
            }
            $table->index('detected_at');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('CREATE UNIQUE INDEX ta_contract_changes_endpoint_city_lang_old_new_unique ON ta_contract_changes (endpoint(255), city_id(50), lang(10), old_payload_hash(64), new_payload_hash(64))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ta_contract_changes');
    }
};
