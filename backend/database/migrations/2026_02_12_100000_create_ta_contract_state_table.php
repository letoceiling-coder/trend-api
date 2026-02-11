<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ta_contract_state')) {
            return;
        }
        Schema::create('ta_contract_state', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 512)->index();
            $table->string('city_id', 50)->nullable();
            $table->string('lang', 10)->nullable();
            $table->string('last_payload_hash', 64);
            $table->json('last_top_keys')->nullable();
            $table->json('last_data_keys')->nullable();
            $table->timestamp('updated_at');

            $table->unique(['endpoint', 'city_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ta_contract_state');
    }
};
