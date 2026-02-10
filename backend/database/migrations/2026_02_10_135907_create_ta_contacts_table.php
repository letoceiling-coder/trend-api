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
        Schema::create('ta_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('city_id');
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->json('data_json');
            $table->string('hash', 64);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['city_id', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_contacts');
    }
};
