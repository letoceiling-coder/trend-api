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
        Schema::create('ta_unit_measurements', function (Blueprint $table) {
            $table->string('id', 50)->primary(); // External ID from API
            $table->string('name')->nullable();
            $table->string('code', 50)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('measurement', 50)->nullable();
            $table->json('raw')->nullable(); // Full object from API
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_unit_measurements');
    }
};
