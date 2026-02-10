<?php

namespace Tests\Unit\Domain\TrendAgent\Sync;

use App\Domain\TrendAgent\Sync\SyncRunner;
use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use App\Models\Domain\TrendAgent\TaBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class TrendAgentSyncBlocksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trendagent.api.core', 'https://api.trendagent.ru');
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
    }

    public function test_shape_detector_finds_items_array(): void
    {
        $mockData = [
            'items' => [
                ['block_id' => 'b1', 'title' => 'Block 1'],
                ['block_id' => 'b2', 'title' => 'Block 2'],
            ],
        ];

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn($mockData);

        $mockHttp = Mockery::mock(TrendHttpClient::class);
        $mockHttp->shouldReceive('get')
            ->once()
            ->andReturn($mockResponse);

        $syncRunner = app(SyncRunner::class);
        $service = new TrendAgentSyncService($mockHttp, $syncRunner);

        $result = $service->syncBlocksSearch(['count' => 10, 'max_pages' => 1], '58c665588b6aa52311afa01b', 'ru', false);
        $run = $result['run'];

        $this->assertEquals('success', $run->status);
        $this->assertEquals(2, $run->items_fetched);
        $this->assertEquals(2, $run->items_saved);
        $this->assertEquals(1, TaBlock::count());
    }

    public function test_shape_detector_finds_nested_data_items(): void
    {
        $mockData = [
            'data' => [
                'items' => [
                    ['_id' => 'b3', 'title' => 'Block 3', 'min_price' => 5000000],
                ],
            ],
        ];

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn($mockData);

        $mockHttp = Mockery::mock(TrendHttpClient::class);
        $mockHttp->shouldReceive('get')
            ->once()
            ->andReturn($mockResponse);

        $syncRunner = app(SyncRunner::class);
        $service = new TrendAgentSyncService($mockHttp, $syncRunner);

        $result = $service->syncBlocksSearch(['count' => 10, 'max_pages' => 1], '58c665588b6aa52311afa01b', 'ru', false);
        $run = $result['run'];

        $this->assertEquals('success', $run->status);
        $this->assertEquals(1, $run->items_fetched);
        $this->assertDatabaseHas('ta_blocks', [
            'block_id' => 'b3',
            'title' => 'Block 3',
            'min_price' => 5000000,
        ]);
    }

    public function test_shape_detector_fails_on_invalid_response(): void
    {
        $mockData = [
            'some_key' => 'value',
            'another_key' => ['not', 'blocks'],
        ];

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn($mockData);

        $mockHttp = Mockery::mock(TrendHttpClient::class);
        $mockHttp->shouldReceive('get')
            ->once()
            ->andReturn($mockResponse);

        $syncRunner = app(SyncRunner::class);
        $service = new TrendAgentSyncService($mockHttp, $syncRunner);

        $result = $service->syncBlocksSearch(['count' => 10, 'max_pages' => 1], '58c665588b6aa52311afa01b', 'ru', false);
        $run = $result['run'];

        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('Unable to detect blocks array', $run->error_message);
        $this->assertEquals(0, TaBlock::count());
    }

    public function test_sync_blocks_extracts_fields_with_fallbacks(): void
    {
        $mockData = [
            'items' => [
                [
                    'block_id' => 'b4',
                    'guid' => 'test-block-guid',
                    'name' => 'Test Block',
                    'kind' => 'residential',
                    'price_from' => 3000000,
                    'price_to' => 10000000,
                    'geo' => ['lat' => 59.9311, 'lng' => 30.3609],
                    'developer' => 'Test Developer',
                ],
            ],
        ];

        $mockResponse = Mockery::mock(Response::class);
        $mockResponse->shouldReceive('json')->andReturn($mockData);

        $mockHttp = Mockery::mock(TrendHttpClient::class);
        $mockHttp->shouldReceive('get')
            ->once()
            ->andReturn($mockResponse);

        $syncRunner = app(SyncRunner::class);
        $service = new TrendAgentSyncService($mockHttp, $syncRunner);

        $result = $service->syncBlocksSearch(['count' => 10, 'max_pages' => 1], '58c665588b6aa52311afa01b', 'ru', false);
        $run = $result['run'];

        $this->assertEquals('success', $run->status);

        $block = TaBlock::where('block_id', 'b4')->first();
        $this->assertNotNull($block);
        $this->assertEquals('test-block-guid', $block->guid);
        $this->assertEquals('Test Block', $block->title);
        $this->assertEquals('residential', $block->kind);
        $this->assertEquals(3000000, $block->min_price);
        $this->assertEquals(10000000, $block->max_price);
        $this->assertEquals('59.9311000', $block->lat);
        $this->assertEquals('30.3609000', $block->lng);
        $this->assertEquals('Test Developer', $block->developer_name);
    }

    public function test_sync_blocks_upserts_on_duplicate_block_id(): void
    {
        // First sync
        $mockData1 = [
            'items' => [
                ['block_id' => 'b5', 'title' => 'Original Title', 'min_price' => 5000000],
            ],
        ];

        $mockResponse1 = Mockery::mock(Response::class);
        $mockResponse1->shouldReceive('json')->andReturn($mockData1);

        $mockHttp = Mockery::mock(TrendHttpClient::class);
        $mockHttp->shouldReceive('get')
            ->once()
            ->andReturn($mockResponse1);

        $syncRunner = app(SyncRunner::class);
        $service = new TrendAgentSyncService($mockHttp, $syncRunner);

        $service->syncBlocksSearch(['count' => 10, 'max_pages' => 1], '58c665588b6aa52311afa01b', 'ru', false);

        $this->assertEquals(1, TaBlock::where('block_id', 'b5')->count());
        $block = TaBlock::where('block_id', 'b5')->first();
        $this->assertEquals('Original Title', $block->title);

        // Second sync with updated data
        $mockData2 = [
            'items' => [
                ['block_id' => 'b5', 'title' => 'Updated Title', 'min_price' => 6000000],
            ],
        ];

        $mockResponse2 = Mockery::mock(Response::class);
        $mockResponse2->shouldReceive('json')->andReturn($mockData2);

        $mockHttp2 = Mockery::mock(TrendHttpClient::class);
        $mockHttp2->shouldReceive('get')
            ->once()
            ->andReturn($mockResponse2);

        $service2 = new TrendAgentSyncService($mockHttp2, $syncRunner);
        $service2->syncBlocksSearch(['count' => 10, 'max_pages' => 1], '58c665588b6aa52311afa01b', 'ru', false);

        // Should still have only one block
        $this->assertEquals(1, TaBlock::where('block_id', 'b5')->count());
        $block = TaBlock::where('block_id', 'b5')->first();
        $this->assertEquals('Updated Title', $block->title);
        $this->assertEquals(6000000, $block->min_price);
    }
}
