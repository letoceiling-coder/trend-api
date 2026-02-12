<?php

namespace App\Domain\TrendAgent\Sync;

use App\Domain\TrendAgent\Quality\DataQualityRunner;
use App\Models\Domain\TrendAgent\TaSyncRun;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncRunner
{
    /**
     * Start a new sync run
     */
    public function startRun(string $scope, ?string $cityId = null, ?string $lang = null): TaSyncRun
    {
        return TaSyncRun::create([
            'provider' => 'trendagent',
            'scope' => $scope,
            'city_id' => $cityId,
            'lang' => $lang,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark sync run as successful
     */
    public function finishSuccess(TaSyncRun $run, int $itemsFetched, int $itemsSaved): TaSyncRun
    {
        $run->update([
            'status' => 'success',
            'finished_at' => now(),
            'items_fetched' => $itemsFetched,
            'items_saved' => $itemsSaved,
        ]);

        $this->runLightQualityChecks($run->scope, 20);

        return $run->fresh();
    }

    /**
     * Run light data quality checks for the given scope (e.g. last 20 records).
     * Does not throw; failures are logged only.
     */
    protected function runLightQualityChecks(string $scope, int $limit = 20): void
    {
        try {
            $runner = app(DataQualityRunner::class);
            $runner->runScope($scope, $limit, 100);
        } catch (Throwable $e) {
            Log::warning('TrendAgent light quality check failed after sync', [
                'scope' => $scope,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark sync run as failed. Masks tokens in error message and context.
     *
     * @param  string|null  $errorCode  Optional code (e.g. normalization_failed, detect_blocks_array)
     */
    public function finishFail(TaSyncRun $run, Throwable $e, array $context = [], ?string $errorCode = null): TaSyncRun
    {
        $errorMessage = $this->sanitizeMessage($e->getMessage());
        $sanitizedContext = $this->sanitizeContext($context);

        $run->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $errorMessage,
            'error_context' => $sanitizedContext,
            'error_code' => $errorCode,
        ]);

        Log::error('TrendAgent sync failed', [
            'scope' => $run->scope,
            'city_id' => $run->city_id,
            'lang' => $run->lang,
            'run_id' => $run->id,
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
        ]);

        return $run->fresh();
    }

    /**
     * Sanitize message to remove tokens
     */
    protected function sanitizeMessage(string $message): string
    {
        return preg_replace(
            '/(auth_token|refresh_token|token|access_token|Bearer\s+)[\w\-\.]+/i',
            '$1***',
            $message
        );
    }

    /**
     * Sanitize context array to remove tokens
     */
    protected function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeMessage($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContext($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
