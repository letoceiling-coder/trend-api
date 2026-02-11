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
        if (Schema::hasTable('ta_directories')) {
            return;
        }
        Schema::create('ta_directories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100); // 'rooms', 'deadlines', 'regions', etc.
            $table->string('city_id', 50);
            $table->string('lang', 10);
            $table->json('payload'); // Full directory data
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['type', 'city_id', 'lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_directories');
    }
};
