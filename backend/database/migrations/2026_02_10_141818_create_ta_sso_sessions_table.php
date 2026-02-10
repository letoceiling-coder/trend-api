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
        Schema::create('ta_sso_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('trendagent');
            $table->string('phone');
            $table->string('city_id')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('refresh_expires_at')->nullable();
            $table->dateTime('last_login_at');
            $table->dateTime('last_auth_token_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'phone']);
            $table->index(['provider', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_sso_sessions');
    }
};
