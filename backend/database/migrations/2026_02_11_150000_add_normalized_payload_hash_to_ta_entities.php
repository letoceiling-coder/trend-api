<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ta_blocks', function (Blueprint $table) {
            if (! Schema::hasColumn('ta_blocks', 'normalized')) {
                if (Schema::hasColumn('ta_blocks', 'raw')) {
                    $table->json('normalized')->nullable()->after('raw');
                } else {
                    $table->json('normalized')->nullable();
                }
            }
            if (! Schema::hasColumn('ta_blocks', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable()->after('normalized');
            }
        });

        Schema::table('ta_apartments', function (Blueprint $table) {
            if (! Schema::hasColumn('ta_apartments', 'normalized')) {
                if (Schema::hasColumn('ta_apartments', 'raw')) {
                    $table->json('normalized')->nullable()->after('raw');
                } else {
                    $table->json('normalized')->nullable();
                }
            }
            if (! Schema::hasColumn('ta_apartments', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable()->after('normalized');
            }
        });

        Schema::table('ta_block_details', function (Blueprint $table) {
            if (! Schema::hasColumn('ta_block_details', 'normalized')) {
                if (Schema::hasColumn('ta_block_details', 'apartments_min_price_payload')) {
                    $table->json('normalized')->nullable()->after('apartments_min_price_payload');
                } else {
                    $table->json('normalized')->nullable();
                }
            }
            if (! Schema::hasColumn('ta_block_details', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable()->after('normalized');
            }
        });

        Schema::table('ta_apartment_details', function (Blueprint $table) {
            if (! Schema::hasColumn('ta_apartment_details', 'normalized')) {
                if (Schema::hasColumn('ta_apartment_details', 'prices_graph_payload')) {
                    $table->json('normalized')->nullable()->after('prices_graph_payload');
                } else {
                    $table->json('normalized')->nullable();
                }
            }
            if (! Schema::hasColumn('ta_apartment_details', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable()->after('normalized');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ta_blocks', function (Blueprint $table) {
            $table->dropColumn(['normalized', 'payload_hash']);
        });
        Schema::table('ta_apartments', function (Blueprint $table) {
            $table->dropColumn(['normalized', 'payload_hash']);
        });
        Schema::table('ta_block_details', function (Blueprint $table) {
            $table->dropColumn(['normalized', 'payload_hash']);
        });
        Schema::table('ta_apartment_details', function (Blueprint $table) {
            $table->dropColumn(['normalized', 'payload_hash']);
        });
    }
};
