<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ta_payload_cache', function (Blueprint $table) {
            $table->string('endpoint', 512)->nullable()->after('scope');
            $table->unsignedSmallInteger('http_status')->nullable()->after('endpoint');
            $table->string('payload_hash', 64)->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('ta_payload_cache', function (Blueprint $table) {
            $table->dropColumn(['endpoint', 'http_status', 'payload_hash']);
        });
    }
};
