<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Writes worker heartbeat to cache so health can verify queue worker is alive.
 * Dispatched every minute by scheduler; when worker runs it, cache is updated.
 */
class TaQueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const CACHE_KEY = 'ta:queue:worker_heartbeat';
    public const TTL_SECONDS = 600;

    public function handle(): void
    {
        Cache::put(self::CACHE_KEY, now(), self::TTL_SECONDS);
    }
}
