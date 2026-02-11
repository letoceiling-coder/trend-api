<?php

namespace Tests\Feature\Api;

use App\Models\Domain\TrendAgent\TaApartment;
use App\Models\Domain\TrendAgent\TaApartmentDetail;
use App\Models\Domain\TrendAgent\TaBlock;
use App\Models\Domain\TrendAgent\TaBlockDetail;
use App\Models\Domain\TrendAgent\TaDirectory;
use App\Models\Domain\TrendAgent\TaUnitMeasurement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
        Config::set('trendagent.default_lang', 'ru');
    }

    public function test_ta_blocks_index_returns_200_with_data_and_meta(): void
    {
        TaBlock::create([
            'block_id' => 'b1',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'title' => 'Test Block',
            'min_price' => 1000000,
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->getJson('/api/ta/blocks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'block_id', 'title', 'city_id', 'lang', 'min_price']],
                'meta' => ['pagination' => ['total', 'count', 'offset']],
            ]);
        $this->assertSame(1, $response->json('meta.pagination.total'));
    }

    public function test_ta_blocks_index_pagination_params(): void
    {
        TaBlock::create([
            'block_id' => 'b1',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);
        TaBlock::create([
            'block_id' => 'b2',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->getJson('/api/ta/blocks?count=1&offset=1');

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('meta.pagination.total'));
        $this->assertSame(1, $response->json('meta.pagination.count'));
        $this->assertSame(1, $response->json('meta.pagination.offset'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_ta_blocks_index_validation_errors(): void
    {
        $response = $this->getJson('/api/ta/blocks?sort_order=invalid');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sort_order']);
    }

    public function test_ta_blocks_index_validates_lang(): void
    {
        $response = $this->getJson('/api/ta/blocks?lang=de');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['lang']);
    }

    public function test_ta_blocks_show_returns_200(): void
    {
        TaBlock::create([
            'block_id' => 'block-123',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'title' => 'One Block',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->getJson('/api/ta/blocks/block-123');

        $response->assertStatus(200)
            ->assertJsonPath('data.block_id', 'block-123')
            ->assertJsonPath('data.title', 'One Block');
    }

    public function test_ta_blocks_show_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/ta/blocks/nonexistent-id');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Block not found']);
    }

    public function test_ta_blocks_show_returns_detail_when_exists(): void
    {
        TaBlock::create([
            'block_id' => 'block-with-detail',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'title' => 'Block With Detail',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);
        TaBlockDetail::create([
            'block_id' => 'block-with-detail',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'unified_payload' => ['description' => 'Extended info'],
            'fetched_at' => now(),
        ]);

        $response = $this->getJson('/api/ta/blocks/block-with-detail');

        $response->assertStatus(200)
            ->assertJsonPath('data.block_id', 'block-with-detail')
            ->assertJsonPath('data.detail.block_id', 'block-with-detail')
            ->assertJsonPath('data.detail.unified_payload.description', 'Extended info');
    }

    public function test_ta_apartments_index_returns_200_with_meta(): void
    {
        TaApartment::create([
            'apartment_id' => 'a1',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'fetched_at' => now(),
        ]);
        TaApartment::create([
            'apartment_id' => 'a2',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'fetched_at' => now(),
        ]);

        $response = $this->getJson('/api/ta/apartments?count=1&offset=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['pagination' => ['total', 'count', 'offset']],
            ]);
        $this->assertSame(2, $response->json('meta.pagination.total'));
        $this->assertSame(1, $response->json('meta.pagination.count'));
        $this->assertSame(1, $response->json('meta.pagination.offset'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_ta_apartments_show_returns_200_when_found(): void
    {
        TaApartment::create([
            'apartment_id' => 'apt-1',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'title' => 'Apt 1',
            'price' => 5000000,
            'fetched_at' => now(),
        ]);

        $response = $this->getJson('/api/ta/apartments/apt-1');

        $response->assertStatus(200)
            ->assertJsonPath('data.apartment_id', 'apt-1')
            ->assertJsonPath('data.title', 'Apt 1');
    }

    public function test_ta_apartments_show_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/ta/apartments/nonexistent');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Apartment not found']);
    }

    public function test_ta_apartments_show_returns_detail_when_exists(): void
    {
        TaApartment::create([
            'apartment_id' => 'apt-with-detail',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'title' => 'Apt With Detail',
            'price' => 6000000,
            'fetched_at' => now(),
        ]);
        TaApartmentDetail::create([
            'apartment_id' => 'apt-with-detail',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'unified_payload' => ['floor_plan' => 'plan-url'],
            'fetched_at' => now(),
        ]);

        $response = $this->getJson('/api/ta/apartments/apt-with-detail');

        $response->assertStatus(200)
            ->assertJsonPath('data.apartment_id', 'apt-with-detail')
            ->assertJsonPath('data.detail.apartment_id', 'apt-with-detail')
            ->assertJsonPath('data.detail.unified_payload.floor_plan', 'plan-url');
    }

    public function test_ta_blocks_refresh_returns_200_queued_true(): void
    {
        Queue::fake();
        Config::set('internal.api_key', 'test-internal-key');
        TaBlock::create([
            'block_id' => 'block-refresh',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'title' => 'Block to refresh',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->postJson('/api/ta/blocks/block-refresh/refresh', [], [
            'X-Internal-Key' => 'test-internal-key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('meta.job', 'SyncBlockDetailJob')
            ->assertJsonPath('meta.id', 'block-refresh');
    }

    public function test_ta_apartments_refresh_returns_200_queued_true(): void
    {
        Config::set('internal.api_key', 'test-internal-key');
        TaApartment::create([
            'apartment_id' => 'apt-refresh',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'title' => 'Apt to refresh',
            'fetched_at' => now(),
        ]);

        $response = $this->postJson('/api/ta/apartments/apt-refresh/refresh', [], [
            'X-Internal-Key' => 'test-internal-key',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('meta.job', 'SyncApartmentDetailJob')
            ->assertJsonPath('meta.id', 'apt-refresh');
    }

    public function test_ta_blocks_refresh_returns_401_without_valid_key(): void
    {
        Config::set('internal.api_key', 'required-key');
        TaBlock::create([
            'block_id' => 'b1',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->postJson('/api/ta/blocks/b1/refresh', [], [
            'X-Internal-Key' => 'wrong-key',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_ta_blocks_refresh_returns_404_when_block_not_found(): void
    {
        Config::set('internal.api_key', 'test-internal-key');

        $response = $this->postJson('/api/ta/blocks/nonexistent-block/refresh', [], [
            'X-Internal-Key' => 'test-internal-key',
        ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Block not found']);
    }

    public function test_ta_ui_blocks_refresh_returns_200_queued_true(): void
    {
        TaBlock::create([
            'block_id' => 'block-ui-refresh',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'title' => 'Block for ta-ui',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->postJson('/api/ta-ui/blocks/block-ui-refresh/refresh');

        $response->assertStatus(200)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('meta.job', 'SyncBlockDetailJob')
            ->assertJsonPath('meta.id', 'block-ui-refresh');
    }

    public function test_ta_ui_apartments_refresh_returns_200_queued_true(): void
    {
        TaApartment::create([
            'apartment_id' => 'apt-ui-refresh',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'title' => 'Apt for ta-ui',
            'fetched_at' => now(),
        ]);

        $response = $this->postJson('/api/ta-ui/apartments/apt-ui-refresh/refresh');

        $response->assertStatus(200)
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('meta.job', 'SyncApartmentDetailJob')
            ->assertJsonPath('meta.id', 'apt-ui-refresh');
    }

    public function test_ta_ui_blocks_refresh_returns_404_when_not_found(): void
    {
        $response = $this->postJson('/api/ta-ui/blocks/nonexistent-block/refresh');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Block not found']);
    }

    public function test_ta_directories_index_requires_type(): void
    {
        $response = $this->getJson('/api/ta/directories');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_ta_directories_index_returns_200_when_found(): void
    {
        TaDirectory::create([
            'type' => 'rooms',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'payload' => ['items' => [['id' => 1, 'name' => '1']]],
            'fetched_at' => now(),
        ]);

        $response = $this->getJson('/api/ta/directories?type=rooms&city_id=58c665588b6aa52311afa01b&lang=ru');

        $response->assertStatus(200)
            ->assertJsonPath('data.type', 'rooms')
            ->assertJsonPath('data.payload.items.0.name', '1');
    }

    public function test_ta_directories_index_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/ta/directories?type=nonexistent_type&city_id=58c665588b6aa52311afa01b&lang=ru');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Directory not found']);
    }

    public function test_ta_unit_measurements_index_returns_200(): void
    {
        TaUnitMeasurement::create([
            'id' => 'um1',
            'name' => 'Square meter',
            'code' => 'm2',
        ]);

        $response = $this->getJson('/api/ta/unit-measurements');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'code']],
                'meta' => ['pagination' => ['total', 'count', 'offset']],
            ]);
    }
}
