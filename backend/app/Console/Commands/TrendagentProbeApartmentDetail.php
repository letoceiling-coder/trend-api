<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Payload\PayloadCacheWriter;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Output\OutputInterface;

class TrendagentProbeApartmentDetail extends Command
{
    protected $signature = 'trendagent:probe:apartment-detail
                            {apartment_id : Apartment ID to probe}
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--save-raw : Save raw responses to ta_payload_cache}
                            {--dump-keys : Print top-level and data keys (1 level)}';

    protected $description = 'Probe apartment detail endpoints to determine real contracts';

    protected array $endpoints = [
        'unified' => '/v4_29/apartments/{id}/unified/',
        'prices_totals' => '/v4_29/prices/apartment/{id}/totals',
        'prices_graph' => '/v4_29/prices/apartment/{id}/graph',
    ];

    public function handle(TrendHttpClient $http): int
    {
        $apartmentId = $this->argument('apartment_id');
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');
        $saveRaw = $this->option('save-raw');
        $dumpKeys = $this->option('dump-keys');
        $verbose = $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');
            return self::FAILURE;
        }

        $coreApi = config('trendagent.api.core', 'https://api.trendagent.ru');

        $this->line('=== TrendAgent Apartment Detail Probe ===');
        $this->line('Apartment ID: ' . $apartmentId);
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);
        $this->newLine();

        $results = [];
        $successCount = 0;

        foreach ($this->endpoints as $key => $endpointTemplate) {
            $endpoint = str_replace('{id}', $apartmentId, $endpointTemplate);
            $url = rtrim($coreApi, '/') . $endpoint;

            $this->line('--- ' . strtoupper($key) . ' ---');
            if ($verbose) {
                $this->line('URL: ' . $url);
            }

            $startTime = microtime(true);
            $queryParams = ['city' => $cityId, 'lang' => $lang];

            try {
                $response = $http->get($url, $queryParams);

                $status = $response->status();
                $data = $response->json();
                $duration = round((microtime(true) - $startTime) * 1000);

                $topLevelKeys = is_array($data) ? array_keys($data) : [];
                $dataKeys = [];
                if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
                    $dataKeys = array_keys($data['data']);
                }

                $result = [
                    'key' => $key,
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'status' => $status,
                    'response' => $data,
                    'top_level_keys' => $topLevelKeys,
                    'data_keys' => $dataKeys,
                    'duration_ms' => $duration,
                    'success' => $status >= 200 && $status < 300,
                ];

                $results[$key] = $result;

                $this->line('Status: ' . $status);
                $this->line('Duration: ' . $duration . 'ms');

                if (! empty($topLevelKeys)) {
                    $this->line('Top-level keys: ' . implode(', ', $topLevelKeys));
                }

                if ($dumpKeys && ! empty($dataKeys)) {
                    $this->line('Data keys (1 level): ' . implode(', ', $dataKeys));
                }

                if ($result['success']) {
                    $this->info('✓ Success');
                    $successCount++;
                } else {
                    $this->warn('✗ Failed');
                }

                if ($saveRaw) {
                    $this->saveToCache($result, $apartmentId, $cityId, $lang);
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
                    'data_keys' => [],
                    'duration_ms' => $duration,
                    'success' => false,
                ];

                if ($saveRaw) {
                    $this->saveToCache($results[$key], $apartmentId, $cityId, $lang);
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

    protected function saveToCache(array $result, string $apartmentId, string $cityId, string $lang): void
    {
        $externalId = $apartmentId . ':' . $result['key'];

        $query = array_filter([
            'city' => $cityId,
            'lang' => $lang,
        ]);

        $payload = [
            '_meta' => [
                'endpoint_name' => $result['key'],
                'url' => $result['url'],
                'status' => $result['status'],
                'query' => $query,
                'received_at' => now()->toIso8601String(),
                'top_level_keys' => $result['top_level_keys'],
                'duration_ms' => $result['duration_ms'],
            ],
            'response' => $result['response'],
        ];

        PayloadCacheWriter::create(
            'probe_apartment_detail',
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
