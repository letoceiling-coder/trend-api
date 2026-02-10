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
        Schema::create('ta_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id');
            $table->unsignedBigInteger('external_id');
            $table->uuid('guid')->nullable();
            $table->json('data_json');
            $table->string('hash', 64);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['city_id', 'external_id']);
            $table->index(['city_id', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_blocks');
    }
};
