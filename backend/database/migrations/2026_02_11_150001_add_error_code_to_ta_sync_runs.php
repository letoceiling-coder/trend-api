<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ta_sync_runs', function (Blueprint $table) {
            $table->string('error_code', 64)->nullable()->after('error_context');
        });
    }

    public function down(): void
    {
        Schema::table('ta_sync_runs', function (Blueprint $table) {
            $table->dropColumn('error_code');
        });
    }
};
