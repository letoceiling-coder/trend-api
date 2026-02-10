<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Recreates ta_apartments for apartments/search sync layer.
     */
    public function up(): void
    {
        Schema::dropIfExists('ta_apartments');

        Schema::create('ta_apartments', function (Blueprint $table) {
            $table->id();
            $table->string('apartment_id', 64);
            $table->string('block_id', 64)->nullable()->index();
            $table->string('guid', 255)->nullable();
            $table->string('title', 512)->nullable();
            $table->unsignedTinyInteger('rooms')->nullable();
            $table->decimal('area_total', 10, 2)->nullable();
            $table->unsignedSmallInteger('floor')->nullable();
            $table->unsignedBigInteger('price')->nullable();
            $table->string('status', 64)->nullable();
            $table->string('city_id', 64);
            $table->string('lang', 8);
            $table->json('raw')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['apartment_id', 'city_id']);
            $table->index(['city_id', 'lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_apartments');
    }
};
