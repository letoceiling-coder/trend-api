<?php

namespace Tests\Unit\Domain\TrendAgent\Sync;

use App\Domain\TrendAgent\Sync\SyncRunner;
use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use App\Models\Domain\TrendAgent\TaApartment;
use App\Models\Domain\TrendAgent\TaPayloadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class TrendAgentSyncApartmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('trendagent.api.core', 'https://api.trendagent.ru');
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
        Config::set('trendagent.default_lang', 'ru');
    }

    /** Shape detector: items at top level */
    public function test_shape_detector_finds_items_array(): void
    {
        $syncRunner = app(SyncRunner::class);
        $httpMock = Mockery::mock(TrendHttpClient::class);
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $data = [
            'items' => [
                ['apartment_id' => 'apt1', 'rooms' => 2],
                ['id' => 'apt2', 'price' => 1000000],
            ],
        ];

        $result = $service->detectApartmentsArray($data);
        $this->assertNotNull($result);
        $this->assertCount(2, $result);
        $this->assertEquals('apt1', $result[0]['apartment_id']);
        $this->assertEquals('apt2', $result[1]['id']);
    }

    /** Shape detector: data.results */
    public function test_shape_detector_finds_data_results(): void
    {
        $syncRunner = app(SyncRunner::class);
        $httpMock = Mockery::mock(TrendHttpClient::class);
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $data = [
            'data' => [
                'results' => [
                    ['_id' => 'r1', 'block_id' => 'b1'],
                ],
            ],
        ];

        $result = $service->detectApartmentsArray($data);
        $this->assertNotNull($result);
        $this->assertCount(1, $result);
        $this->assertEquals('r1', $result[0]['_id']);
    }

    /** Shape detector: data.items */
    public function test_shape_detector_finds_data_items(): void
    {
        $syncRunner = app(SyncRunner::class);
        $httpMock = Mockery::mock(TrendHttpClient::class);
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $data = [
            'data' => [
                'items' => [
                    ['id' => 'i1', 'rooms' => 1],
                ],
            ],
        ];

        $result = $service->detectApartmentsArray($data);
        $this->assertNotNull($result);
        $this->assertCount(1, $result);
    }

    /** Shape detector: apartments at top level */
    public function test_shape_detector_finds_apartments_key(): void
    {
        $syncRunner = app(SyncRunner::class);
        $httpMock = Mockery::mock(TrendHttpClient::class);
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $data = [
            'apartments' => [
                ['apartment_id' => 'a1', 'price' => 5000000],
            ],
        ];

        $result = $service->detectApartmentsArray($data);
        $this->assertNotNull($result);
        $this->assertCount(1, $result);
    }

    /** Shape detector: invalid response returns null */
    public function test_shape_detector_returns_null_for_invalid_response(): void
    {
        $syncRunner = app(SyncRunner::class);
        $httpMock = Mockery::mock(TrendHttpClient::class);
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $this->assertNull($service->detectApartmentsArray(['errors' => ['msg']]));
        $this->assertNull($service->detectApartmentsArray(['data' => 'not-array']));
        $this->assertNull($service->detectApartmentsArray([]));
    }

    /** Sync saves 2 apartments and run is success */
    public function test_sync_apartments_saves_two_and_run_success(): void
    {
        $cityId = '58c665588b6aa52311afa01b';
        $lang = 'ru';

        $mockResponse = [
            'data' => [
                'results' => [
                    [
                        'apartment_id' => 'apt-one',
                        'block_id' => 'block-1',
                        'title' => 'Apartment 1',
                        'rooms' => 2,
                        'area_total' => 45.5,
                        'floor' => 5,
                        'price' => 7000000,
                        'status' => 'free',
                    ],
                    [
                        '_id' => 'apt-two',
                        'block_id' => 'block-1',
                        'number' => '42',
                        'rooms' => 1,
                        'price_from' => 5000000,
                    ],
                ],
            ],
        ];

        $httpMock = Mockery::mock(TrendHttpClient::class);
        $httpMock->shouldReceive('get')
            ->once()
            ->andReturn($this->createMockResponse(200, $mockResponse));

        $syncRunner = new SyncRunner();
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $result = $service->syncApartmentsSearch(
            ['count' => 50, 'max_pages' => 1],
            $cityId,
            $lang,
            false
        );

        $run = $result['run'];
        $this->assertEquals('success', $run->status);
        $this->assertEquals(2, $run->items_fetched);
        $this->assertEquals(2, $run->items_saved);

        $this->assertDatabaseHas('ta_apartments', [
            'apartment_id' => 'apt-one',
            'city_id' => $cityId,
        ]);
        $this->assertDatabaseHas('ta_apartments', [
            'apartment_id' => 'apt-two',
            'city_id' => $cityId,
        ]);

        $a1 = TaApartment::where('apartment_id', 'apt-one')->first();
        $this->assertEquals(2, $a1->rooms);
        $this->assertEquals(7000000, $a1->price);
        $this->assertEquals('block-1', $a1->block_id);
    }

    /** Upsert by (apartment_id, city_id) does not create duplicates */
    public function test_sync_apartments_upsert_no_duplicates(): void
    {
        $cityId = '58c665588b6aa52311afa01b';
        $lang = 'ru';

        TaApartment::create([
            'apartment_id' => 'existing-apt',
            'city_id' => $cityId,
            'lang' => $lang,
            'title' => 'Old Title',
            'rooms' => 1,
            'fetched_at' => now()->subDay(),
        ]);

        $mockResponse = [
            'items' => [
                [
                    'apartment_id' => 'existing-apt',
                    'city_id' => $cityId,
                    'title' => 'New Title',
                    'rooms' => 3,
                    'price' => 6000000,
                ],
            ],
        ];

        $httpMock = Mockery::mock(TrendHttpClient::class);
        $httpMock->shouldReceive('get')->once()->andReturn($this->createMockResponse(200, $mockResponse));

        $syncRunner = new SyncRunner();
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $service->syncApartmentsSearch(['count' => 50, 'max_pages' => 1], $cityId, $lang, false);

        $this->assertEquals(1, TaApartment::where('apartment_id', 'existing-apt')->count());
        $updated = TaApartment::where('apartment_id', 'existing-apt')->first();
        $this->assertEquals('New Title', $updated->title);
        $this->assertEquals(3, $updated->rooms);
        $this->assertEquals(6000000, $updated->price);
    }

    /** Probe with --save-raw stores in ta_payload_cache when GET returns items */
    public function test_probe_apartments_search_save_raw_stores_in_cache(): void
    {
        $this->mock(\App\Integrations\TrendAgent\Auth\TrendAuthService::class, function ($mock) {
            $mock->shouldReceive('getAuthToken')->andReturn('fake-auth-token');
        });

        Http::fake([
            '*apartments/search*' => Http::response([
                'data' => [
                    'results' => [
                        ['_id' => 'probe-apt-1', 'price' => 1000000],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('trendagent:probe:apartments-search', [
            '--count' => 2,
            '--offset' => 0,
            '--city' => '58c665588b6aa52311afa01b',
            '--lang' => 'ru',
            '--save-raw' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('ta_payload_cache', [
            'provider' => 'trendagent',
            'scope' => 'probe_apartments_search',
        ]);

        $cache = TaPayloadCache::where('scope', 'probe_apartments_search')->first();
        $this->assertNotNull($cache);
        $payload = json_decode($cache->payload, true);
        $this->assertArrayHasKey('_meta', $payload);
        $this->assertArrayHasKey('response', $payload);
        $this->assertStringContainsString('get:', $cache->external_id);
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
