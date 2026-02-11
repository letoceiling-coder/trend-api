<?php

namespace Tests\Unit\Domain\TrendAgent\Quality;

use App\Domain\TrendAgent\Quality\DataQualityRunner;
use App\Models\Domain\TrendAgent\TaApartment;
use App\Models\Domain\TrendAgent\TaApartmentDetail;
use App\Models\Domain\TrendAgent\TaBlock;
use App\Models\Domain\TrendAgent\TaBlockDetail;
use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataQualityRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_price_negative_fail(): void
    {
        TaBlock::create([
            'block_id' => 'b1',
            'title' => 'Block',
            'city_id' => 'c1',
            'lang' => 'ru',
            'min_price' => -100,
            'raw' => '{}',
            'fetched_at' => now(),
        ]);

        $runner = new DataQualityRunner();
        $runner->checkBlocks(10, 50);

        $this->assertDatabaseHas('ta_data_quality_checks', [
            'scope' => 'blocks',
            'entity_id' => 'b1',
            'check_name' => 'min_price_negative',
            'status' => 'fail',
        ]);
    }

    public function test_blocks_coordinates_invalid_fail(): void
    {
        TaBlock::create([
            'block_id' => 'b2',
            'title' => 'Block',
            'city_id' => 'c1',
            'lang' => 'ru',
            'lat' => 95,
            'lng' => -200,
            'raw' => '{}',
            'fetched_at' => now(),
        ]);

        $runner = new DataQualityRunner();
        $runner->checkBlocks(10, 50);

        $this->assertDatabaseHas('ta_data_quality_checks', [
            'scope' => 'blocks',
            'entity_id' => 'b2',
            'check_name' => 'lat_invalid',
            'status' => 'fail',
        ]);
        $this->assertDatabaseHas('ta_data_quality_checks', [
            'scope' => 'blocks',
            'entity_id' => 'b2',
            'check_name' => 'lng_invalid',
            'status' => 'fail',
        ]);
    }

    public function test_block_detail_missing_unified_payload_fail(): void
    {
        TaBlockDetail::create([
            'block_id' => 'bd1',
            'city_id' => 'c1',
            'lang' => 'ru',
            'unified_payload' => null,
            'fetched_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $runner = new DataQualityRunner();
        $runner->checkBlockDetail(10, 50);

        $this->assertDatabaseHas('ta_data_quality_checks', [
            'scope' => 'block_detail',
            'entity_id' => 'bd1',
            'check_name' => 'unified_payload_missing',
            'status' => 'fail',
        ]);
    }

    public function test_apartment_detail_missing_unified_payload_fail(): void
    {
        TaApartmentDetail::create([
            'apartment_id' => 'apt1',
            'city_id' => 'c1',
            'lang' => 'ru',
            'unified_payload' => null,
            'fetched_at' => now(),
        ]);

        $runner = new DataQualityRunner();
        $runner->checkApartmentDetail(10, 50);

        $this->assertDatabaseHas('ta_data_quality_checks', [
            'scope' => 'apartment_detail',
            'entity_id' => 'apt1',
            'check_name' => 'unified_payload_missing',
            'status' => 'fail',
        ]);
    }

    public function test_apartments_price_negative_fail(): void
    {
        TaApartment::create([
            'apartment_id' => 'a1',
            'block_id' => 'b1',
            'title' => 'Apt',
            'city_id' => 'c1',
            'lang' => 'ru',
            'price' => -50,
            'fetched_at' => now(),
        ]);

        $runner = new DataQualityRunner();
        $runner->checkApartments(10, 50);

        $this->assertDatabaseHas('ta_data_quality_checks', [
            'scope' => 'apartments',
            'entity_id' => 'a1',
            'check_name' => 'price_negative',
            'status' => 'fail',
        ]);
    }
}
