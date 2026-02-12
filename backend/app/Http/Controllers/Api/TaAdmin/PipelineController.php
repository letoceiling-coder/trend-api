<?php

namespace App\Http\Controllers\Api\TaAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\TrendAgent\SyncApartmentsJob;
use App\Jobs\TrendAgent\SyncBlockDetailJob;
use App\Jobs\TrendAgent\SyncBlocksJob;
use App\Jobs\TrendAgent\SyncApartmentDetailJob;
use App\Models\Domain\TrendAgent\TaApartment;
use App\Models\Domain\TrendAgent\TaBlock;
use App\Models\Domain\TrendAgent\TaPipelineRun;
use App\Models\Domain\TrendAgent\TaSyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class PipelineController extends Controller
{
    private const LOCK_TTL_MINUTES = 15;
    private const LOCK_KEY_PREFIX = 'ta:pipeline:lock:';

    /**
     * POST /api/ta/admin/pipeline/run
     * Body: city_id?, lang?, blocks_count?, blocks_pages?, apartments_pages?, dispatch_details?, detail_limit?
     */
    public function run(Request $request): JsonResponse
    {
        $cityId = $request->input('city_id') ?? Config::get('trendagent.default_city_id');
        $lang = $request->input('lang') ?? 'ru';
        $blocksCount = (int) $request->input('blocks_count', 50);
        $blocksPages = (int) $request->input('blocks_pages', 1);
        $apartmentsPages = (int) $request->input('apartments_pages', 1);
        $dispatchDetails = (bool) $request->input('dispatch_details', true);
        $detailLimit = (int) $request->input('detail_limit', 50);

        $lockKey = self::LOCK_KEY_PREFIX . $cityId . ':' . $lang;
        $lockUntil = Cache::get($lockKey);
        if ($lockUntil !== null) {
            $until = is_string($lockUntil) ? $lockUntil : (\Carbon\Carbon::parse($lockUntil)->toIso8601String());
            return response()->json([
                'message' => 'Pipeline already running',
                'meta' => ['lock_until' => $until],
            ], 409);
        }

        $params = [
            'city_id' => $cityId,
            'lang' => $lang,
            'blocks_count' => $blocksCount,
            'blocks_pages' => $blocksPages,
            'apartments_pages' => $apartmentsPages,
            'dispatch_details' => $dispatchDetails,
            'detail_limit' => $detailLimit,
        ];
        $requestedBy = $this->buildRequestedBy($request);
        $isSync = config('queue.default') === 'sync';

        $pipelineRun = TaPipelineRun::createRecord(
            $params,
            $isSync ? 'running' : 'queued',
            $requestedBy
        );

        $lockUntilTime = now()->addMinutes(self::LOCK_TTL_MINUTES);
        Cache::put($lockKey, $lockUntilTime->toIso8601String(), self::LOCK_TTL_MINUTES * 60);

        try {
            if ($isSync) {
                SyncBlocksJob::dispatchSync(
                    $cityId,
                    $lang,
                    $blocksCount,
                    $blocksPages,
                    'list',
                    true,
                    false,
                    $dispatchDetails ? $detailLimit : 0
                );
                SyncApartmentsJob::dispatchSync(
                    $cityId,
                    $lang,
                    50,
                    $apartmentsPages,
                    'price',
                    'asc',
                    true,
                    false,
                    $dispatchDetails ? $detailLimit : 0
                );
                if ($dispatchDetails) {
                    $this->dispatchDetailJobs($cityId, $lang, $detailLimit);
                }
                $pipelineRun->update([
                    'status' => 'success',
                    'finished_at' => now(),
                ]);
                $lastRun = TaSyncRun::query()
                    ->where('provider', 'trendagent')
                    ->orderByDesc('id')
                    ->first();
                $runId = $lastRun ? (string) $lastRun->id : $pipelineRun->id;
                return response()->json([
                    'data' => [
                        'queued' => false,
                        'run_id' => $runId,
                    ],
                    'meta' => [
                        'city_id' => $cityId,
                        'lang' => $lang,
                        'blocks_count' => $blocksCount,
                        'blocks_pages' => $blocksPages,
                        'apartments_pages' => $apartmentsPages,
                        'detail_limit' => $detailLimit,
                    ],
                ]);
            }

            SyncBlocksJob::dispatch(
                $cityId,
                $lang,
                $blocksCount,
                $blocksPages,
                'list',
                true,
                $dispatchDetails,
                $detailLimit
            );
            SyncApartmentsJob::dispatch(
                $cityId,
                $lang,
                50,
                $apartmentsPages,
                'price',
                'asc',
                true,
                $dispatchDetails,
                $detailLimit
            );

            return response()->json([
                'data' => [
                    'queued' => true,
                    'run_id' => $pipelineRun->id,
                ],
                'meta' => [
                    'city_id' => $cityId,
                    'lang' => $lang,
                    'blocks_count' => $blocksCount,
                    'blocks_pages' => $blocksPages,
                    'apartments_pages' => $apartmentsPages,
                    'detail_limit' => $detailLimit,
                ],
            ]);
        } catch (\Throwable $e) {
            $pipelineRun->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => \Illuminate\Support\Str::limit($e->getMessage(), 1000),
            ]);
            Log::error('TrendAgent pipeline failed', [
                'city_id' => $cityId,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            return response()->json([
                'message' => 'Pipeline failed',
            ], 500);
        }
    }

    private function buildRequestedBy(Request $request): ?string
    {
        $ip = $request->ip();
        $ua = $request->userAgent();
        $parts = array_filter([$ip, $ua]);
        return $parts === [] ? null : implode(' ', $parts);
    }

    private function dispatchDetailJobs(string $cityId, string $lang, int $detailLimit): void
    {
        $blockIds = TaBlock::query()
            ->where('city_id', $cityId)
            ->where('lang', $lang)
            ->orderByDesc('updated_at')
            ->limit($detailLimit)
            ->pluck('block_id');
        $delay = 0;
        foreach ($blockIds as $blockId) {
            SyncBlockDetailJob::dispatch($blockId, $cityId, $lang, true)
                ->delay(now()->addSeconds($delay));
            $delay += (int) config('trendagent.queue.detail_job_delay_seconds', 2);
        }
        $apartmentIds = TaApartment::query()
            ->where('city_id', $cityId)
            ->where('lang', $lang)
            ->orderByDesc('updated_at')
            ->limit($detailLimit)
            ->pluck('apartment_id');
        foreach ($apartmentIds as $apartmentId) {
            SyncApartmentDetailJob::dispatch($apartmentId, $cityId, $lang, true)
                ->delay(now()->addSeconds($delay));
            $delay += (int) config('trendagent.queue.detail_job_delay_seconds', 2);
        }
    }
}
