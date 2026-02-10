<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use App\Models\Domain\TrendAgent\TaPayloadCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Output\OutputInterface;

class TrendagentProbeApartmentsSearch extends Command
{
    protected $signature = 'trendagent:probe:apartments-search
                            {--count=50 : Number of items}
                            {--offset=0 : Offset for pagination}
                            {--sort=price : Sort field}
                            {--sort-order=asc : Sort order: asc or desc}
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--save-raw : Save raw response to ta_payload_cache}
                            {--dump-keys : Dump top-level response keys}';

    protected $description = 'Probe apartments/search API to determine real contract';

    public function handle(TrendHttpClient $http, TrendAgentSyncService $sync): int
    {
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');
        $verbose = $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');
            return self::FAILURE;
        }

        $coreApi = config('trendagent.api.core', 'https://api.trendagent.ru');
        $url = rtrim($coreApi, '/') . '/v4_29/apartments/search/';

        $params = [
            'count' => (int) $this->option('count'),
            'offset' => (int) $this->option('offset'),
            'sort' => $this->option('sort'),
            'sort_order' => $this->option('sort-order'),
        ];

        $saveRaw = $this->option('save-raw');
        $dumpKeys = $this->option('dump-keys');

        $this->line('=== TrendAgent Apartments Search Probe ===');
        $this->line('URL: ' . $url);
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);
        $this->line('Params: count=' . $params['count'] . ', offset=' . $params['offset'] . ', sort=' . $params['sort'] . ', sort_order=' . $params['sort_order']);
        $this->newLine();

        $results = [];

        // Try GET
        $this->line('--- Attempting GET request ---');
        $getResult = $this->probeRequest($http, $sync, 'GET', $url, $params, [], $cityId, $lang, $verbose);
        $results['GET'] = $getResult;

        $this->displayResult($getResult, $dumpKeys);

        if ($saveRaw) {
            $this->saveToCache($getResult, $params, $cityId, $lang);
        }

        $needsPost = $getResult['status'] >= 400
            || isset($getResult['response']['errors'])
            || isset($getResult['response']['error'])
            || ! $getResult['items_found'];

        if ($needsPost) {
            $this->newLine();
            $this->line('--- Attempting POST request with JSON body ---');

            $bodyParams = [
                'count' => $params['count'],
                'offset' => $params['offset'],
                'sort' => $params['sort'],
                'sort_order' => $params['sort_order'],
            ];

            $postResult = $this->probeRequest($http, $sync, 'POST', $url, [], $bodyParams, $cityId, $lang, $verbose);
            $results['POST'] = $postResult;

            $this->displayResult($postResult, $dumpKeys);

            if ($saveRaw) {
                $this->saveToCache($postResult, $params, $cityId, $lang);
            }
        } else {
            $this->info('✓ GET request successful, skipping POST');
        }

        $this->newLine();
        $this->line('=== Summary ===');

        foreach ($results as $method => $result) {
            $this->line(sprintf(
                '%s: status=%d, duration_ms=%d, items_found=%s, items_count=%d',
                $method,
                $result['status'],
                $result['duration_ms'],
                $result['items_found'] ? 'YES' : 'NO',
                $result['items_count']
            ));
        }

        $bestMethod = null;
        foreach ($results as $method => $result) {
            if ($result['status'] >= 200 && $result['status'] < 300 && $result['items_found']) {
                $bestMethod = $method;
                break;
            }
        }

        if ($bestMethod) {
            $this->info("\n✓ Best method: " . $bestMethod);
            return self::SUCCESS;
        }

        $this->error("\n✗ No successful method found");
        return self::FAILURE;
    }

    protected function probeRequest(
        TrendHttpClient $http,
        TrendAgentSyncService $sync,
        string $method,
        string $url,
        array $query,
        array $body,
        string $cityId,
        string $lang,
        bool $verbose
    ): array {
        $startTime = microtime(true);

        try {
            if ($method === 'GET') {
                $response = $http->get($url, array_merge($query, ['city' => $cityId, 'lang' => $lang]));
            } else {
                $response = $http->postJson($url, ['city' => $cityId, 'lang' => $lang], $body);
            }

            $status = $response->status();
            $data = $response->json();

            $apartmentsArray = $sync->detectApartmentsArray($data);
            $itemsFound = $apartmentsArray !== null;
            $itemsCount = $itemsFound ? count($apartmentsArray) : 0;
            $topLevelKeys = is_array($data) ? array_keys($data) : [];
            $durationMs = round((microtime(true) - $startTime) * 1000);

            $previewIfNoItems = null;
            if (! $itemsFound && is_array($data)) {
                $preview = json_encode($data, JSON_UNESCAPED_UNICODE);
                $preview = $this->maskTokens($preview);
                $previewIfNoItems = substr($preview, 0, 500);
            }

            return [
                'method' => $method,
                'url' => $url,
                'status' => $status,
                'response' => $data,
                'items_found' => $itemsFound,
                'items_count' => $itemsCount,
                'top_level_keys' => $topLevelKeys,
                'duration_ms' => $durationMs,
                'query' => $this->sanitizeParams($query),
                'body' => $this->sanitizeParams($body),
                'preview_if_no_items' => $previewIfNoItems,
            ];
        } catch (\Throwable $e) {
            $msg = $this->maskTokens($e->getMessage());
            return [
                'method' => $method,
                'url' => $url,
                'status' => 0,
                'response' => ['error' => $msg],
                'items_found' => false,
                'items_count' => 0,
                'top_level_keys' => [],
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
                'query' => $this->sanitizeParams($query),
                'body' => $this->sanitizeParams($body),
                'preview_if_no_items' => $msg,
            ];
        }
    }

    protected function displayResult(array $result, bool $dumpKeys): void
    {
        $this->line('Status: ' . $result['status']);
        $this->line('Duration: ' . $result['duration_ms'] . 'ms');

        if ($dumpKeys && ! empty($result['top_level_keys'])) {
            $this->line('Top-level keys: ' . implode(', ', $result['top_level_keys']));
        }

        $this->line('Items found: ' . ($result['items_found'] ? 'YES' : 'NO'));

        if ($result['items_found']) {
            $this->info('Items count: ' . $result['items_count']);
        } elseif (! empty($result['preview_if_no_items'])) {
            $this->warn('Response preview (masked, first 500 chars):');
            $this->line($result['preview_if_no_items'] . '...');
        }
    }

    protected function saveToCache(array $result, array $params, string $cityId, string $lang): void
    {
        $externalId = sprintf(
            '%s:%d:%d:%s:%s',
            strtolower($result['method']),
            $params['offset'],
            $params['count'],
            $params['sort'],
            $params['sort_order']
        );

        $payload = [
            '_meta' => [
                'method' => $result['method'],
                'url' => $result['url'],
                'status' => $result['status'],
                'query' => $result['query'],
                'body' => $result['body'] ?? null,
                'received_at' => now()->toIso8601String(),
                'top_level_keys' => $result['top_level_keys'],
                'items_found' => $result['items_found'],
                'items_count' => $result['items_count'],
                'duration_ms' => $result['duration_ms'],
                'preview_if_no_items' => $result['preview_if_no_items'] ?? null,
            ],
            'response' => $result['response'],
        ];

        TaPayloadCache::create([
            'provider' => 'trendagent',
            'scope' => 'probe_apartments_search',
            'external_id' => $externalId,
            'city_id' => $cityId,
            'lang' => $lang,
            'payload' => json_encode($payload),
            'fetched_at' => now(),
        ]);

        $this->line('✓ Saved to ta_payload_cache (external_id: ' . $externalId . ')');
    }

    protected function sanitizeParams(array $params): array
    {
        $sanitized = [];
        foreach ($params as $key => $value) {
            if ($key === 'auth_token') {
                continue;
            }
            $sanitized[$key] = $value;
        }
        return $sanitized;
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
