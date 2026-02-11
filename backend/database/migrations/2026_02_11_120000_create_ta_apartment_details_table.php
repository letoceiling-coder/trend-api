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
        if (Schema::hasTable('ta_apartment_details')) {
            return;
        }
        Schema::create('ta_apartment_details', function (Blueprint $table) {
            $table->id();
            $table->string('apartment_id', 64);
            $table->string('city_id', 64);
            $table->string('lang', 8);
            $table->json('unified_payload')->nullable();
            $table->json('prices_totals_payload')->nullable();
            $table->json('prices_graph_payload')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['apartment_id', 'city_id', 'lang']);
            $table->index(['apartment_id']);
            $table->index(['city_id']);
            $table->index(['lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_apartment_details');
    }
};
