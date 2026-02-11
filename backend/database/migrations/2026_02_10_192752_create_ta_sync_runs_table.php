<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('ta_sync_runs')) {
            return;
        }
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

            // SQLite: full composite index. MySQL: prefix index to stay under key length limit (767/3072).
            if (Schema::getConnection()->getDriverName() !== 'mysql') {
                $table->index(['provider', 'scope', 'city_id', 'lang', 'started_at']);
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX ta_sync_runs_provider_scope_city_lang_started_index ON ta_sync_runs (provider(50), scope(50), city_id(50), lang(10), started_at)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_sync_runs');
    }
};
