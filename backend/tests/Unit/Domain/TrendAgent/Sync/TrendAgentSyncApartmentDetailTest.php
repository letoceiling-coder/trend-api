<?php

namespace Tests\Unit\Domain\TrendAgent\Sync;

use App\Domain\TrendAgent\Sync\SyncRunner;
use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use App\Models\Domain\TrendAgent\TaApartmentDetail;
use App\Models\Domain\TrendAgent\TaPayloadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class TrendAgentSyncApartmentDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('trendagent.api.core', 'https://api.trendagent.ru');
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
        Config::set('trendagent.default_lang', 'ru');
    }

    public function test_probe_apartment_detail_saves_raw_and_summary(): void
    {
        $this->mock(\App\Integrations\TrendAgent\Auth\TrendAuthService::class, function ($mock) {
            $mock->shouldReceive('ensureAuthenticated')->andReturn('fake-token');
        });

        \Illuminate\Support\Facades\Http::fake([
            '*apartments/*/unified*' => \Illuminate\Support\Facades\Http::response(['data' => ['_id' => 'apt1']], 200),
            '*prices/apartment/*/totals*' => \Illuminate\Support\Facades\Http::response([], 404),
            '*prices/apartment/*/graph*' => \Illuminate\Support\Facades\Http::response([], 404),
        ]);

        $this->artisan('trendagent:probe:apartment-detail', [
            'apartment_id' => '65c9f8c023bccf8025bfdbc2',
            '--city' => '58c665588b6aa52311afa01b',
            '--lang' => 'ru',
            '--save-raw' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('ta_payload_cache', [
            'provider' => 'trendagent',
            'scope' => 'probe_apartment_detail',
        ]);

        $cache = TaPayloadCache::where('scope', 'probe_apartment_detail')->first();
        $this->assertNotNull($cache);
        $payload = json_decode($cache->payload, true);
        $this->assertArrayHasKey('_meta', $payload);
        $this->assertArrayHasKey('response', $payload);
        $this->assertStringContainsString('65c9f8c023bccf8025bfdbc2:unified', $cache->external_id);
    }

    public function test_sync_apartment_detail_unified_ok_prices_404_creates_record_prices_null(): void
    {
        $apartmentId = '65c9f8c023bccf8025bfdbc2';

        $httpMock = Mockery::mock(TrendHttpClient::class);

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/apartments\/.*\/unified/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['data' => ['_id' => $apartmentId, 'number' => '15']]));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/prices\/apartment\/.*\/totals/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(404, []));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/prices\/apartment\/.*\/graph/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(404, []));

        $syncRunner = new SyncRunner();
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $result = $service->syncApartmentDetail($apartmentId, null, null, false);

        $this->assertEquals('success', $result['run']->status);
        $this->assertEquals(1, $result['endpoints_ok']);
        $this->assertEquals(2, $result['endpoints_failed']);

        $this->assertDatabaseHas('ta_apartment_details', [
            'apartment_id' => $apartmentId,
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
        ]);

        $detail = TaApartmentDetail::where('apartment_id', $apartmentId)->first();
        $this->assertNotNull($detail->unified_payload);
        $this->assertNull($detail->prices_totals_payload);
        $this->assertNull($detail->prices_graph_payload);
    }

    public function test_sync_apartment_detail_unified_500_run_failed(): void
    {
        $apartmentId = '65c9f8c023bccf8025bfdbc2';

        $httpMock = Mockery::mock(TrendHttpClient::class);

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/apartments\/.*\/unified/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(500, ['error' => 'Internal Server Error']));

        $syncRunner = new SyncRunner();
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $result = $service->syncApartmentDetail($apartmentId, null, null, false);

        $this->assertEquals('failed', $result['run']->status);
        $this->assertStringContainsString('Required endpoint unified failed', $result['run']->error_message);

        $this->assertDatabaseMissing('ta_apartment_details', [
            'apartment_id' => $apartmentId,
        ]);
    }

    public function test_sync_apartment_detail_upsert_no_duplicates(): void
    {
        $apartmentId = '65c9f8c023bccf8025bfdbc2';

        TaApartmentDetail::create([
            'apartment_id' => $apartmentId,
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'unified_payload' => ['old' => 'data'],
            'fetched_at' => now()->subDay(),
        ]);

        $httpMock = Mockery::mock(TrendHttpClient::class);
        $httpMock->shouldReceive('get')->andReturn($this->createMockResponse(200, ['data' => ['_id' => $apartmentId]]));

        $syncRunner = new SyncRunner();
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $service->syncApartmentDetail($apartmentId, null, null, false);

        $this->assertEquals(1, TaApartmentDetail::where('apartment_id', $apartmentId)->count());
        $detail = TaApartmentDetail::where('apartment_id', $apartmentId)->first();
        $this->assertEquals(['data' => ['_id' => $apartmentId]], $detail->unified_payload);
    }

    protected function createMockResponse(int $status, array $json): Response
    {
        return new Response(
            new \GuzzleHttp\Psr7\Response($status, [], json_encode($json))
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
