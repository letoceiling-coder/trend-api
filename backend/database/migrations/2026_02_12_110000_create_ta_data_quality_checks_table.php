<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ta_data_quality_checks')) {
            return;
        }
        Schema::create('ta_data_quality_checks', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 64)->index();
            $table->string('entity_id', 128)->nullable()->index();
            $table->string('city_id', 50)->nullable();
            $table->string('lang', 10)->nullable();
            $table->string('check_name', 128);
            $table->string('status', 16); // pass, warn, fail
            $table->string('message', 1024);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['scope', 'status']);
            $table->index(['scope', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ta_data_quality_checks');
    }
};
