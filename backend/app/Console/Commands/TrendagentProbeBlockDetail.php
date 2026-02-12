<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Payload\PayloadCacheWriter;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Output\OutputInterface;

class TrendagentProbeBlockDetail extends Command
{
    protected $signature = 'trendagent:probe:block-detail
                            {block_id : Block ID to probe}
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--save-raw : Save raw responses to ta_payload_cache}';

    protected $description = 'Probe block detail endpoints to determine real contracts';

    protected array $endpoints = [
        'unified' => '/v4_29/blocks/{id}/unified/',
        'advantages' => '/v4_29/blocks/{id}/advantages/',
        'nearby_places' => '/v4_29/blocks/{id}/nearby_places/',
        'bank' => '/v4_29/blocks/{id}/bank/',
        'geo_buildings' => '/v4_29/blocks/{id}/geo/buildings/',
        'apartments_min_price' => '/v4_29/blocks/{id}/apartments/min-price/',
    ];

    public function handle(TrendHttpClient $http): int
    {
        $blockId = $this->argument('block_id');
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');
        $saveRaw = $this->option('save-raw');
        $verbose = $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');
            return self::FAILURE;
        }

        $coreApi = config('trendagent.api.core', 'https://api.trendagent.ru');

        $this->line('=== TrendAgent Block Detail Probe ===');
        $this->line('Block ID: ' . $blockId);
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);
        $this->newLine();

        $results = [];
        $successCount = 0;

        foreach ($this->endpoints as $key => $endpointTemplate) {
            $endpoint = str_replace('{id}', $blockId, $endpointTemplate);
            $url = rtrim($coreApi, '/') . $endpoint;

            $this->line('--- ' . strtoupper($key) . ' ---');
            if ($verbose) {
                $this->line('URL: ' . $url);
            }

            $startTime = microtime(true);

            try {
                $queryParams = ['city' => $cityId, 'lang' => $lang];

                // Special params for unified endpoint
                if ($key === 'unified') {
                    $queryParams['formating'] = 'true';
                    $queryParams['ch'] = 'false';
                }

                $response = $http->get($url, $queryParams);

                $status = $response->status();
                $data = $response->json();
                $duration = round((microtime(true) - $startTime) * 1000);

                $topLevelKeys = is_array($data) ? array_keys($data) : [];

                $result = [
                    'key' => $key,
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'status' => $status,
                    'response' => $data,
                    'top_level_keys' => $topLevelKeys,
                    'duration_ms' => $duration,
                    'success' => $status >= 200 && $status < 300,
                ];

                $results[$key] = $result;

                $this->line('Status: ' . $status);
                $this->line('Duration: ' . $duration . 'ms');

                if (! empty($topLevelKeys)) {
                    $this->line('Top-level keys: ' . implode(', ', $topLevelKeys));
                }

                if ($result['success']) {
                    $this->info('✓ Success');
                    $successCount++;
                } else {
                    $this->warn('✗ Failed');
                }

                if ($saveRaw) {
                    $this->saveToCache($result, $blockId, $cityId, $lang);
                }
            } catch (\Throwable $e) {
                $duration = round((microtime(true) - $startTime) * 1000);
                $this->error('✗ Exception: ' . $this->maskTokens($e->getMessage()));
                $this->line('Duration: ' . $duration . 'ms');

                $results[$key] = [
                    'key' => $key,
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'status' => 0,
                    'response' => ['error' => $e->getMessage()],
                    'top_level_keys' => [],
                    'duration_ms' => $duration,
                    'success' => false,
                ];

                if ($saveRaw) {
                    $this->saveToCache($results[$key], $blockId, $cityId, $lang);
                }
            }

            $this->newLine();
        }

        // Summary
        $this->line('=== Summary ===');
        $this->line('Total endpoints: ' . count($this->endpoints));
        $this->line('Successful: ' . $successCount);
        $this->line('Failed: ' . (count($this->endpoints) - $successCount));

        foreach ($results as $result) {
            $statusIcon = $result['success'] ? '✓' : '✗';
            $this->line(sprintf(
                '%s %s: status=%d, duration=%dms',
                $statusIcon,
                strtoupper($result['key']),
                $result['status'],
                $result['duration_ms']
            ));
        }

        return $successCount > 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function saveToCache(array $result, string $blockId, string $cityId, string $lang): void
    {
        $externalId = $blockId . ':' . $result['key'];

        $payload = [
            '_meta' => [
                'endpoint' => $result['endpoint'],
                'url' => $result['url'],
                'status' => $result['status'],
                'received_at' => now()->toIso8601String(),
                'top_level_keys' => $result['top_level_keys'],
                'duration_ms' => $result['duration_ms'],
                'success' => $result['success'],
            ],
            'response' => $result['response'],
        ];

        PayloadCacheWriter::create(
            'probe_block_detail',
            $externalId,
            PayloadCacheWriter::endpointFromUrl($result['url']),
            (int) $result['status'],
            $payload,
            $cityId,
            $lang,
        );

        $this->line('  → Saved to cache (id: ' . $externalId . ')');
    }

    protected function maskTokens(string $text): string
    {
        return preg_replace(
            '/(auth_token|refresh_token|token|access_token|Bearer\s+)[\w\-\.]+/i',
            '$1***',
            $text
        );
    }
}
