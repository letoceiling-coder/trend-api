<?php

namespace Tests\Unit\Domain\TrendAgent;

use App\Console\Kernel as ConsoleKernel;
use App\Domain\TrendAgent\TaCoverageService;
use App\Domain\TrendAgent\TaHealthService;
use App\Jobs\TaQueueHeartbeatJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TaHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaHealthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $coverage = $this->createMock(TaCoverageService::class);
        $coverage->method('getCoverageData')->willReturn([
            'blocks_total' => 0,
            'blocks_with_detail_fresh' => 0,
            'blocks_without_detail' => 0,
            'apartments_total' => 0,
            'apartments_with_detail_fresh' => 0,
            'apartments_without_detail' => 0,
        ]);
        $this->service = new TaHealthService($coverage);
    }

    public function test_get_runtime_data_schedule_ok_false_when_cache_empty(): void
    {
        Cache::forget(ConsoleKernel::SCHEDULE_LAST_RUN_CACHE_KEY);
        Config::set('queue.default', 'sync');

        $runtime = $this->service->getRuntimeData('sync');

        $this->assertFalse($runtime['schedule_ok']);
        $this->assertNull($runtime['last_schedule_run_at']);
    }

    public function test_get_runtime_data_schedule_ok_true_when_last_run_within_two_minutes(): void
    {
        $now = now();
        Cache::put(ConsoleKernel::SCHEDULE_LAST_RUN_CACHE_KEY, $now, 600);
        Config::set('queue.default', 'sync');

        $runtime = $this->service->getRuntimeData('sync');

        $this->assertTrue($runtime['schedule_ok']);
        $this->assertNotNull($runtime['last_schedule_run_at']);
    }

    public function test_get_runtime_data_schedule_ok_false_when_last_run_older_than_two_minutes(): void
    {
        $old = now()->subMinutes(3);
        Cache::put(ConsoleKernel::SCHEDULE_LAST_RUN_CACHE_KEY, $old, 600);
        Config::set('queue.default', 'sync');

        $runtime = $this->service->getRuntimeData('sync');

        $this->assertFalse($runtime['schedule_ok']);
        $this->assertNotNull($runtime['last_schedule_run_at']);
    }

    public function test_get_runtime_data_queue_ok_true_when_sync_connection(): void
    {
        Cache::forget(TaQueueHeartbeatJob::CACHE_KEY);
        Config::set('queue.default', 'sync');

        $runtime = $this->service->getRuntimeData('sync');

        $this->assertTrue($runtime['queue_ok']);
        $this->assertNull($runtime['last_queue_heartbeat_at']);
    }

    public function test_get_runtime_data_queue_ok_true_when_redis_and_heartbeat_fresh(): void
    {
        $now = now();
        Cache::put(TaQueueHeartbeatJob::CACHE_KEY, $now, 600);
        Config::set('queue.default', 'redis');

        $runtime = $this->service->getRuntimeData('redis');

        $this->assertTrue($runtime['queue_ok']);
        $this->assertNotNull($runtime['last_queue_heartbeat_at']);
    }

    public function test_get_runtime_data_queue_ok_false_when_redis_and_no_heartbeat(): void
    {
        Cache::forget(TaQueueHeartbeatJob::CACHE_KEY);
        Config::set('queue.default', 'redis');

        $runtime = $this->service->getRuntimeData('redis');

        $this->assertFalse($runtime['queue_ok']);
        $this->assertNull($runtime['last_queue_heartbeat_at']);
    }

    public function test_get_runtime_data_queue_ok_false_when_redis_and_heartbeat_stale(): void
    {
        $old = now()->subMinutes(5);
        Cache::put(TaQueueHeartbeatJob::CACHE_KEY, $old, 600);
        Config::set('queue.default', 'redis');

        $runtime = $this->service->getRuntimeData('redis');

        $this->assertFalse($runtime['queue_ok']);
        $this->assertNotNull($runtime['last_queue_heartbeat_at']);
    }

    public function test_get_last_schedule_run_at_returns_null_when_empty(): void
    {
        Cache::forget(ConsoleKernel::SCHEDULE_LAST_RUN_CACHE_KEY);
        $this->assertNull($this->service->getLastScheduleRunAt());
    }

    public function test_get_last_schedule_run_at_returns_iso_string_when_set(): void
    {
        $now = now();
        Cache::put(ConsoleKernel::SCHEDULE_LAST_RUN_CACHE_KEY, $now, 600);
        $result = $this->service->getLastScheduleRunAt();
        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result);
    }

    public function test_get_last_queue_heartbeat_at_returns_null_when_empty(): void
    {
        Cache::forget(TaQueueHeartbeatJob::CACHE_KEY);
        $this->assertNull($this->service->getLastQueueHeartbeatAt());
    }

    public function test_get_health_data_includes_relogin_counts(): void
    {
        $data = $this->service->getHealthData();

        $this->assertArrayHasKey('relogin_attempts_last_24h', $data);
        $this->assertArrayHasKey('relogin_failed_last_24h', $data);
        $this->assertIsInt($data['relogin_attempts_last_24h']);
        $this->assertIsInt($data['relogin_failed_last_24h']);
    }
}
