<?php

namespace Tests\Unit\Domain\TrendAgent\Sync;

use App\Domain\TrendAgent\Sync\SyncRunner;
use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use App\Models\Domain\TrendAgent\TaDirectory;
use App\Models\Domain\TrendAgent\TaUnitMeasurement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class TrendAgentSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trendagent.api.core', 'https://api.trendagent.ru');
        Config::set('trendagent.api.apartment', 'https://apartment-api.trendagent.ru');
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
    }

    public function test_sync_unit_measurements_saves_records_and_creates_sync_run(): void
    {
        $mockData = [
            ['_id' => 'unit1', 'name' => 'Square meter', 'code' => 'm2', 'currency' => null, 'measurement' => 'area'],
            ['_id' => 'unit2', 'name' => 'Percent', 'code' => '%', 'currency' => null, 'measurement' => 'ratio'],
        ];

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn($mockData);

        $mockHttp = Mockery::mock(TrendHttpClient::class);
        $mockHttp->shouldReceive('get')
            ->once()
            ->with(
                Mockery::on(fn ($url) => str_contains($url, '/v4_29/unit_measurements')),
                Mockery::on(fn ($query) => $query['city'] === '58c665588b6aa52311afa01b' && $query['lang'] === 'ru')
            )
            ->andReturn($mockResponse);

        $syncRunner = app(SyncRunner::class);
        $service = new TrendAgentSyncService($mockHttp, $syncRunner);

        $run = $service->syncUnitMeasurements('58c665588b6aa52311afa01b', 'ru', false);

        $this->assertEquals('success', $run->status);
        $this->assertEquals(2, $run->items_fetched);
        $this->assertEquals(2, $run->items_saved);

        $this->assertDatabaseHas('ta_unit_measurements', ['id' => 'unit1', 'name' => 'Square meter', 'code' => 'm2']);
        $this->assertDatabaseHas('ta_unit_measurements', ['id' => 'unit2', 'name' => 'Percent', 'code' => '%']);
    }

    public function test_sync_directories_saves_by_type_and_upserts(): void
    {
        $mockData = [
            'rooms' => [
                ['code' => 1, 'title' => '1 room'],
                ['code' => 2, 'title' => '2 rooms'],
            ],
            'deadlines' => [
                ['code' => '2026_Q4', 'title' => 'Q4 2026'],
            ],
        ];

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn($mockData);

        $mockHttp = Mockery::mock(TrendHttpClient::class);
        $mockHttp->shouldReceive('get')
            ->twice()
            ->with(
                Mockery::on(fn ($url) => str_contains($url, '/v1/directories')),
                Mockery::on(fn ($query) =>
                    $query['city'] === '58c665588b6aa52311afa01b'
                    && $query['lang'] === 'ru'
                    && isset($query['types'])
                )
            )
            ->andReturn($mockResponse);

        $syncRunner = app(SyncRunner::class);
        $service = new TrendAgentSyncService($mockHttp, $syncRunner);

        $run = $service->syncDirectories(['rooms', 'deadlines'], '58c665588b6aa52311afa01b', 'ru', false);

        $this->assertEquals('success', $run->status);
        $this->assertEquals(2, $run->items_fetched);
        $this->assertEquals(2, $run->items_saved);

        $this->assertDatabaseHas('ta_directories', [
            'type' => 'rooms',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
        ]);

        $this->assertDatabaseHas('ta_directories', [
            'type' => 'deadlines',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
        ]);

        // Test upsert: sync again with same data
        $run2 = $service->syncDirectories(['rooms'], '58c665588b6aa52311afa01b', 'ru', false);

        $this->assertEquals('success', $run2->status);
        // Should still have only one row for rooms
        $this->assertEquals(1, TaDirectory::where('type', 'rooms')->count());
    }
}
