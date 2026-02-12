<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ta_relogin_events')) {
            return;
        }
        Schema::create('ta_relogin_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('attempted_at');
            $table->boolean('success');
            $table->string('city_id', 64)->nullable()->index();
            $table->timestamps();

            $table->index(['attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ta_relogin_events');
    }
};
