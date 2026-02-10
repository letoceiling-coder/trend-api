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
        Schema::create('ta_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('trendagent');
            $table->string('scope', 100); // 'directories', 'blocks_list', 'unit_measurements', etc.
            $table->string('city_id', 50)->nullable();
            $table->string('lang', 10)->nullable();
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('items_fetched')->default(0);
            $table->unsignedInteger('items_saved')->default(0);
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();
            $table->timestamps();

            $table->index(['provider', 'scope', 'city_id', 'lang', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_sync_runs');
    }
};
