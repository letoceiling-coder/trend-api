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
        Schema::create('ta_blocks', function (Blueprint $table) {
            $table->id(); // Internal auto-increment ID
            $table->string('block_id', 32)->unique(); // Real block ID from TrendAgent
            $table->string('guid', 100)->nullable()->index(); // Slug from URL object/{guid}
            $table->string('title')->nullable();
            $table->string('city_id', 64);
            $table->string('lang', 8);
            $table->string('kind', 50)->nullable(); // Type of block (if present in JSON)
            $table->string('status', 50)->nullable();
            $table->bigInteger('min_price')->nullable()->index();
            $table->bigInteger('max_price')->nullable();
            $table->string('deadline', 100)->nullable();
            $table->string('developer_name')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->longText('raw'); // Full JSON object
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['city_id', 'lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ta_blocks');
    }
};
