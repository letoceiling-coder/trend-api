<?php

namespace App\Domain\TrendAgent\Sync;

use App\Models\Domain\TrendAgent\TaSyncRun;
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

        return $run->fresh();
    }

    /**
     * Mark sync run as failed. Masks tokens in error message and context.
     */
    public function finishFail(TaSyncRun $run, Throwable $e, array $context = []): TaSyncRun
    {
        $errorMessage = $this->sanitizeMessage($e->getMessage());
        $sanitizedContext = $this->sanitizeContext($context);

        $run->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $errorMessage,
            'error_context' => $sanitizedContext,
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
