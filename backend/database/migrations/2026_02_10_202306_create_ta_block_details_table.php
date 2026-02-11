<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ta_block_details')) {
            return;
        }
        Schema::create('ta_block_details', function (Blueprint $table) {
            $table->id();
            $table->string('block_id', 32)->unique(); // FK-like to ta_blocks.block_id
            $table->string('city_id', 64);
            $table->string('lang', 8);
            $table->json('unified_payload')->nullable();
            $table->json('advantages_payload')->nullable();
            $table->json('nearby_places_payload')->nullable();
            $table->json('bank_payload')->nullable();
            $table->json('geo_buildings_payload')->nullable();
            $table->json('apartments_min_price_payload')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['city_id', 'lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_block_details');
    }
};
