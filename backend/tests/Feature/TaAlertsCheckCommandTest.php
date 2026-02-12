<?php

namespace Tests\Feature;

use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use App\Models\Domain\TrendAgent\TaSyncRun;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaAlertsCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['trendagent.alerts.telegram_bot_token' => 'fake-token']);
        config(['trendagent.alerts.telegram_chat_id' => '-999']);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_sends_alert_when_failed_sync_runs_in_period(): void
    {
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 0,
            'items_saved' => 0,
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 0,
            'items_saved' => 0,
        ]);

        $this->artisan('ta:alerts:check', ['--since' => '15m'])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $text = $request->data()['text'] ?? '';
            return str_contains($text, 'Failed sync runs') && str_contains($text, '2');
        });
    }

    public function test_sends_alert_when_quality_fail_count_increased(): void
    {
        Cache::put('ta_alerts_last_quality_fail_count', 2, now()->addHours(48));
        TaDataQualityCheck::create([
            'scope' => 'blocks',
            'entity_id' => 'e1',
            'check_name' => 'test',
            'status' => 'fail',
            'message' => 'fail',
            'created_at' => now(),
        ]);
        TaDataQualityCheck::create([
            'scope' => 'blocks',
            'entity_id' => 'e2',
            'check_name' => 'test',
            'status' => 'fail',
            'message' => 'fail',
            'created_at' => now(),
        ]);
        TaDataQualityCheck::create([
            'scope' => 'apartments',
            'entity_id' => 'e3',
            'check_name' => 'test',
            'status' => 'fail',
            'message' => 'fail',
            'created_at' => now(),
        ]);

        $this->artisan('ta:alerts:check', ['--since' => '15m'])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $text = $request->data()['text'] ?? '';
            return str_contains($text, 'Quality fail count') && str_contains($text, 'increased');
        });
    }

    public function test_sends_alert_when_no_recent_successful_sync_for_blocks_apartments(): void
    {
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => now()->subMinutes(60),
            'finished_at' => now()->subMinutes(60),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'apartments',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => now()->subMinutes(60),
            'finished_at' => now()->subMinutes(60),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);

        $this->artisan('ta:alerts:check', ['--since' => '30m'])
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $text = $request->data()['text'] ?? '';
            return str_contains($text, 'No successful sync') && str_contains($text, 'blocks');
        });
    }

    public function test_no_alert_when_no_failures(): void
    {
        Cache::put('ta_alerts_last_quality_fail_count', 10, now()->addHours(48));
        TaDataQualityCheck::create([
            'scope' => 'blocks',
            'entity_id' => 'e1',
            'check_name' => 'test',
            'status' => 'fail',
            'message' => 'fail',
            'created_at' => now(),
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'apartments',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);

        $this->artisan('ta:alerts:check', ['--since' => '15m'])
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_dedupe_prevents_duplicate_alert(): void
    {
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'apartments',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 0,
            'items_saved' => 0,
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 0,
            'items_saved' => 0,
        ]);

        $this->artisan('ta:alerts:check', ['--since' => '15m'])->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertNotNull(Cache::get('ta:alert:dedupe:failed_runs'), 'Dedupe cache must be set after first send');

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $this->artisan('ta:alerts:check', ['--since' => '15m'])->assertSuccessful();
        Http::assertSentCount(0);
    }

    public function test_quiet_hours_suppresses_alert_and_increments_counter(): void
    {
        Cache::forget('ta:alert:quiet_suppressed');
        config(['trendagent.alerts.quiet_hours' => '23:00-08:00']);
        config(['trendagent.alerts.quiet_hours_timezone' => 'Europe/Kiev']);
        $this->travelTo(now()->setTimezone('Europe/Kiev')->setHour(2)->setMinute(0)->setSecond(0));

        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'apartments',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
            'items_fetched' => 0,
            'items_saved' => 0,
        ]);

        $this->artisan('ta:alerts:check', ['--since' => '15m'])->assertSuccessful();

        Http::assertNothingSent();
        $suppressed = Cache::get('ta:alert:quiet_suppressed');
        $this->assertIsArray($suppressed);
        $this->assertSame(1, $suppressed['count'] ?? 0);
        $this->assertSame(1, $suppressed['reasons']['failed_runs'] ?? 0);

        $this->travelBack();
    }

    public function test_after_quiet_hours_sends_summary(): void
    {
        config(['trendagent.alerts.quiet_hours' => '23:00-08:00']);
        config(['trendagent.alerts.quiet_hours_timezone' => 'Europe/Kiev']);
        Cache::forget('ta:alert:dedupe:failed_runs');
        Cache::forget('ta:alert:dedupe:no_success');
        Cache::forget('ta:alert:dedupe:quality_growth');
        Cache::put('ta:alert:quiet_suppressed', [
            'count' => 5,
            'reasons' => ['failed_runs' => 3, 'no_success' => 2],
        ], 43200);
        Cache::put('ta_alerts_last_quality_fail_count', 10, now()->addHours(48));
        $base = Carbon::parse('2026-02-10 10:00:00', 'Europe/Kiev');
        Carbon::setTestNow($base);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'blocks',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => $base->copy()->subMinutes(2),
            'finished_at' => $base->copy()->subMinutes(2),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);
        TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => 'apartments',
            'city_id' => 'c1',
            'lang' => 'ru',
            'status' => 'success',
            'started_at' => $base->copy()->subMinutes(2),
            'finished_at' => $base->copy()->subMinutes(2),
            'items_fetched' => 1,
            'items_saved' => 1,
        ]);

        $this->artisan('ta:alerts:check', ['--since' => '15m'])->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $text = $request->data()['text'] ?? '';
            return str_contains($text, 'During quiet hours we suppressed')
                && str_contains($text, '5');
        });
        $this->assertNull(Cache::get('ta:alert:quiet_suppressed'));

        Carbon::setTestNow();
    }
}
