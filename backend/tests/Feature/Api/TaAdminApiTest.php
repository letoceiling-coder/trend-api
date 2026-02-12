<?php

namespace Tests\Feature\Api;

use App\Models\Domain\TrendAgent\TaContractChange;
use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use App\Models\Domain\TrendAgent\TaSyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaAdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('internal.api_key', 'test-admin-key');
    }

    public function test_admin_sync_runs_without_key_returns_401(): void
    {
        $response = $this->getJson('/api/ta/admin/sync-runs');
        $response->assertStatus(401)->assertJson(['message' => 'Unauthorized']);
    }

    public function test_admin_sync_runs_with_key_returns_200_and_structure(): void
    {
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 10,
            'items_saved' => 10,
        ]);

        $response = $this->getJson('/api/ta/admin/sync-runs', [
            'X-Internal-Key' => 'test-admin-key',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'scope',
                        'status',
                        'items_fetched',
                        'items_saved',
                        'error_message',
                        'created_at',
                        'finished_at',
                    ],
                ],
                'meta' => ['count', 'since_hours'],
            ]);
        $this->assertSame('blocks', $response->json('data.0.scope'));
    }

    public function test_admin_contract_changes_with_key_returns_200(): void
    {
        TaContractChange::create([
            'endpoint' => '/v4/blocks/search',
            'city_id' => 'c1',
            'lang' => 'ru',
            'old_payload_hash' => 'old',
            'new_payload_hash' => 'new',
            'detected_at' => now(),
        ]);

        $response = $this->getJson('/api/ta/admin/contract-changes', [
            'X-Internal-Key' => 'test-admin-key',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_quality_checks_with_key_returns_200(): void
    {
        TaDataQualityCheck::create([
            'scope' => 'blocks',
            'entity_id' => 'b1',
            'check_name' => 'required_title',
            'status' => 'pass',
            'message' => 'OK',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/ta/admin/quality-checks', [
            'X-Internal-Key' => 'test-admin-key',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_health_with_key_returns_200_and_expected_keys(): void
    {
        $response = $this->getJson('/api/ta/admin/health', [
            'X-Internal-Key' => 'test-admin-key',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'sync' => [
                        'blocks' => ['last_success_at'],
                        'apartments' => ['last_success_at'],
                        'block_detail' => ['last_success_at'],
                        'apartment_detail' => ['last_success_at'],
                    ],
                    'contract_changes_last_24h_count',
                    'quality_fail_last_24h_count',
                    'pipeline_last_24h_count',
                    'pipeline_failed_last_24h_count',
                    'queue' => ['connection', 'queue_name'],
                    'coverage' => [
                        'blocks_total',
                        'blocks_with_detail_fresh',
                        'blocks_without_detail',
                        'apartments_total',
                        'apartments_with_detail_fresh',
                        'apartments_without_detail',
                    ],
                    'runtime' => [
                        'schedule_ok',
                        'queue_ok',
                        'redis_ok',
                        'last_schedule_run_at',
                        'last_queue_heartbeat_at',
                    ],
                ],
                'meta',
            ]);
    }

    public function test_admin_coverage_with_key_returns_200_and_structure(): void
    {
        $response = $this->getJson('/api/ta/admin/coverage', [
            'X-Internal-Key' => 'test-admin-key',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'blocks_total',
                    'blocks_with_detail_fresh',
                    'blocks_without_detail',
                    'apartments_total',
                    'apartments_with_detail_fresh',
                    'apartments_without_detail',
                ],
                'meta',
            ]);
        $data = $response->json('data');
        $this->assertIsInt($data['blocks_total']);
        $this->assertIsInt($data['blocks_with_detail_fresh']);
        $this->assertIsInt($data['blocks_without_detail']);
        $this->assertIsInt($data['apartments_total']);
        $this->assertIsInt($data['apartments_with_detail_fresh']);
        $this->assertIsInt($data['apartments_without_detail']);
    }

    public function test_admin_coverage_without_key_returns_401(): void
    {
        $response = $this->getJson('/api/ta/admin/coverage');
        $response->assertStatus(401);
    }

    public function test_admin_pipeline_run_without_key_returns_401(): void
    {
        $response = $this->postJson('/api/ta/admin/pipeline/run', []);
        $response->assertStatus(401);
    }

    public function test_admin_pipeline_run_with_key_dispatches_jobs_when_queue_not_sync(): void
    {
        Config::set('queue.default', 'redis');
        Queue::fake();

        $response = $this->postJson('/api/ta/admin/pipeline/run', [
            'city_id' => 'c1',
            'lang' => 'ru',
            'blocks_count' => 10,
            'blocks_pages' => 1,
            'dispatch_details' => false,
        ], [
            'X-Internal-Key' => 'test-admin-key',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.queued', true);
        $runId = $response->json('data.run_id');
        $this->assertNotEmpty($runId);
        Queue::assertPushed(\App\Jobs\TrendAgent\SyncBlocksJob::class);
        Queue::assertPushed(\App\Jobs\TrendAgent\SyncApartmentsJob::class);

        $this->assertDatabaseHas('ta_pipeline_runs', [
            'id' => $runId,
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'queued',
        ]);
    }

    public function test_admin_pipeline_run_second_request_same_city_lang_returns_409(): void
    {
        Config::set('queue.default', 'redis');
        Queue::fake();

        $this->postJson('/api/ta/admin/pipeline/run', [
            'city_id' => 'c1',
            'lang' => 'ru',
            'blocks_count' => 10,
            'blocks_pages' => 1,
            'dispatch_details' => false,
        ], [
            'X-Internal-Key' => 'test-admin-key',
        ])->assertStatus(200);

        $response = $this->postJson('/api/ta/admin/pipeline/run', [
            'city_id' => 'c1',
            'lang' => 'ru',
            'blocks_count' => 10,
            'blocks_pages' => 1,
            'dispatch_details' => false,
        ], [
            'X-Internal-Key' => 'test-admin-key',
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Pipeline already running');
        $response->assertJsonStructure(['meta' => ['lock_until']]);
    }

    public function test_admin_pipeline_run_creates_ta_pipeline_runs_record(): void
    {
        Config::set('queue.default', 'redis');
        Queue::fake();

        $this->postJson('/api/ta/admin/pipeline/run', [
            'city_id' => 'c2',
            'lang' => 'en',
            'blocks_count' => 5,
            'dispatch_details' => false,
        ], [
            'X-Internal-Key' => 'test-admin-key',
        ])->assertStatus(200);

        $this->assertDatabaseHas('ta_pipeline_runs', [
            'city_id' => 'c2',
            'lang' => 'en',
            'status' => 'queued',
        ]);
        $run = \App\Models\Domain\TrendAgent\TaPipelineRun::where('city_id', 'c2')->where('lang', 'en')->first();
        $this->assertNotNull($run);
        $this->assertSame('queued', $run->status);
        $this->assertIsArray($run->params);
        $this->assertSame(5, $run->params['blocks_count'] ?? 0);
    }
}
