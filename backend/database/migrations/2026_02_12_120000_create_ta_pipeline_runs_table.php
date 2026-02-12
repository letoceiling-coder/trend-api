<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ta_pipeline_runs')) {
            return;
        }
        Schema::create('ta_pipeline_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('city_id', 64)->nullable()->index();
            $table->string('lang', 8)->nullable()->index();
            $table->string('requested_by', 512)->nullable();
            $table->json('params')->nullable();
            $table->string('status', 32)->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['city_id', 'lang', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ta_pipeline_runs');
    }
};
