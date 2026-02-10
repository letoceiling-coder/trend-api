<?php

namespace Tests\Unit\Domain\TrendAgent\Sync;

use App\Domain\TrendAgent\Sync\SyncRunner;
use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use App\Models\Domain\TrendAgent\TaBlockDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class TrendAgentSyncBlockDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('trendagent.api.core', 'https://api.trendagent.ru');
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
        Config::set('trendagent.default_lang', 'ru');
    }

    public function test_sync_block_detail_saves_all_endpoints()
    {
        $blockId = '65c8b45523bccfa820bfaf73';

        // Mock HTTP client
        $httpMock = Mockery::mock(TrendHttpClient::class);

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/unified/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['id' => $blockId, 'title' => 'Test Block']));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/advantages/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['items' => ['advantage1', 'advantage2']]));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/nearby_places/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['places' => ['place1']]));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/bank/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['bank_info' => 'data']));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/geo\/buildings/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['buildings' => []]));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/apartments\/min-price/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['min_price' => 1000000]));

        $syncRunner = new SyncRunner();
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $run = $service->syncBlockDetail($blockId, null, null, false);

        $this->assertEquals('success', $run->status);
        $this->assertEquals(6, $run->items_fetched);
        $this->assertEquals(1, $run->items_saved);

        $this->assertDatabaseHas('ta_block_details', [
            'block_id' => $blockId,
        ]);

        $detail = TaBlockDetail::where('block_id', $blockId)->first();
        $this->assertNotNull($detail->unified_payload);
        $this->assertNotNull($detail->advantages_payload);
        $this->assertNotNull($detail->nearby_places_payload);
        $this->assertNotNull($detail->bank_payload);
        $this->assertNotNull($detail->geo_buildings_payload);
        $this->assertNotNull($detail->apartments_min_price_payload);
    }

    public function test_sync_block_detail_fails_when_unified_endpoint_fails()
    {
        $blockId = '65c8b45523bccfa820bfaf73';

        $httpMock = Mockery::mock(TrendHttpClient::class);

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/unified/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(401, ['message' => 'Unauthorized']));

        $syncRunner = new SyncRunner();
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $run = $service->syncBlockDetail($blockId, null, null, false);

        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('Required endpoint unified failed', $run->error_message);
    }

    public function test_sync_block_detail_continues_when_optional_endpoint_fails()
    {
        $blockId = '65c8b45523bccfa820bfaf73';

        $httpMock = Mockery::mock(TrendHttpClient::class);

        // Unified succeeds (required)
        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/unified/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['id' => $blockId]));

        // Advantages fails (optional)
        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/advantages/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(404, []));

        // Other optional endpoints succeed
        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/nearby_places/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['places' => []]));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/bank/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['bank' => 'test']));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/geo\/buildings/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['buildings' => []]));

        $httpMock->shouldReceive('get')
            ->with(Mockery::pattern('/apartments\/min-price/'), Mockery::any())
            ->once()
            ->andReturn($this->createMockResponse(200, ['price' => 1000000]));

        $syncRunner = new SyncRunner();
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $run = $service->syncBlockDetail($blockId, null, null, false);

        $this->assertEquals('success', $run->status);
        $this->assertEquals(5, $run->items_fetched); // 6 total - 1 failed optional

        $detail = TaBlockDetail::where('block_id', $blockId)->first();
        $this->assertNotNull($detail->unified_payload);
        $this->assertNull($detail->advantages_payload); // This one failed
        $this->assertNotNull($detail->nearby_places_payload);
    }

    public function test_sync_block_detail_upserts_existing_record()
    {
        $blockId = '65c8b45523bccfa820bfaf73';

        // Create existing record
        TaBlockDetail::create([
            'block_id' => $blockId,
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'unified_payload' => ['old' => 'data'],
            'fetched_at' => now()->subDay(),
        ]);

        $httpMock = Mockery::mock(TrendHttpClient::class);

        $httpMock->shouldReceive('get')
            ->andReturn($this->createMockResponse(200, ['new' => 'data']));

        $syncRunner = new SyncRunner();
        $service = new TrendAgentSyncService($httpMock, $syncRunner);

        $run = $service->syncBlockDetail($blockId, null, null, false);

        $this->assertEquals('success', $run->status);

        // Should still be only 1 record
        $this->assertEquals(1, TaBlockDetail::where('block_id', $blockId)->count());

        $detail = TaBlockDetail::where('block_id', $blockId)->first();
        $this->assertEquals(['new' => 'data'], $detail->unified_payload);
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
