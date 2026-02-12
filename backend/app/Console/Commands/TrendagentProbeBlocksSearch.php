<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Payload\PayloadCacheWriter;
use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Console\Output\OutputInterface;

class TrendagentProbeBlocksSearch extends Command
{
    protected $signature = 'trendagent:probe:blocks-search
                            {--show-type=list : Show type: list, map, or plans}
                            {--count=20 : Number of items}
                            {--offset=0 : Offset for pagination}
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--sort=price : Sort field}
                            {--sort-order=asc : Sort order: asc or desc}
                            {--method=auto : HTTP method: auto, get, or post}
                            {--save-raw : Save raw response to ta_payload_cache}
                            {--dump-keys : Dump top-level response keys}';

    protected $description = 'Probe blocks/search API to determine real contract';

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
        $url = rtrim($coreApi, '/') . '/v4_29/blocks/search';

        $params = [
            'show_type' => $this->option('show-type'),
            'count' => (int) $this->option('count'),
            'offset' => (int) $this->option('offset'),
            'sort' => $this->option('sort'),
            'sort_order' => $this->option('sort-order'),
        ];

        $method = $this->option('method');
        $saveRaw = $this->option('save-raw');
        $dumpKeys = $this->option('dump-keys');

        $this->line('=== TrendAgent Blocks Search Probe ===');
        $this->line('URL: ' . $url);
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);
        $this->line('Method: ' . $method);
        $this->newLine();

        $results = [];

        // Try GET
        if ($method === 'auto' || $method === 'get') {
            $this->line('--- Attempting GET request ---');
            $getResult = $this->probeRequest($http, $sync, 'GET', $url, $params, [], $cityId, $lang, $verbose);
            $results['GET'] = $getResult;

            $this->displayResult($getResult, $dumpKeys);

            if ($saveRaw) {
                $this->saveToCache($getResult, $params, $cityId, $lang);
            }

            // Check if GET was successful
            $needsPost = $method === 'auto' && ($getResult['status'] >= 400 || 
                         isset($getResult['response']['errors']) || 
                         isset($getResult['response']['error']) ||
                         ! $getResult['items_found']);

            if (! $needsPost && $method === 'auto') {
                $this->info('✓ GET request successful, skipping POST');
                return self::SUCCESS;
            }
        }

        // Try POST
        if ($method === 'auto' || $method === 'post') {
            $this->newLine();
            $this->line('--- Attempting POST request with JSON body ---');

            // Move query params to body for POST
            $bodyParams = [
                'show_type' => $params['show_type'],
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
        }

        // Summary
        $this->newLine();
        $this->line('=== Summary ===');

        foreach ($results as $method => $result) {
            $status = $result['status'];
            $itemsFound = $result['items_found'] ? 'YES' : 'NO';
            $itemsCount = $result['items_count'];

            $this->line(sprintf(
                '%s: status=%d, items_found=%s, items_count=%d',
                $method,
                $status,
                $itemsFound,
                $itemsCount
            ));
        }

        // Determine best method
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
        } else {
            $this->error("\n✗ No successful method found");
            return self::FAILURE;
        }
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

            // Use shape detector from sync service
            $reflection = new \ReflectionClass($sync);
            $detectMethod = $reflection->getMethod('detectBlocksArray');
            $detectMethod->setAccessible(true);
            $blocksArray = $detectMethod->invoke($sync, $data);

            $itemsFound = $blocksArray !== null;
            $itemsCount = $itemsFound ? count($blocksArray) : 0;

            $topLevelKeys = is_array($data) ? array_keys($data) : [];

            $duration = round((microtime(true) - $startTime) * 1000);

            return [
                'method' => $method,
                'url' => $url,
                'status' => $status,
                'response' => $data,
                'items_found' => $itemsFound,
                'items_count' => $itemsCount,
                'top_level_keys' => $topLevelKeys,
                'duration_ms' => $duration,
                'query' => $this->sanitizeParams($query),
                'body' => $this->sanitizeParams($body),
            ];
        } catch (\Throwable $e) {
            return [
                'method' => $method,
                'url' => $url,
                'status' => 0,
                'response' => ['error' => $e->getMessage()],
                'items_found' => false,
                'items_count' => 0,
                'top_level_keys' => [],
                'duration_ms' => round((microtime(true) - $startTime) * 1000),
                'query' => $this->sanitizeParams($query),
                'body' => $this->sanitizeParams($body),
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
        } else {
            // Show preview of response
            $preview = json_encode($result['response'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $preview = $this->maskTokens($preview);
            $preview = substr($preview, 0, 300);
            $this->warn('Response preview (first 300 chars):');
            $this->line($preview . '...');
        }
    }

    protected function saveToCache(array $result, array $originalParams, string $cityId, string $lang): void
    {
        $externalId = sprintf(
            '%s:%s:%d:%d',
            strtolower($result['method']),
            $originalParams['show_type'],
            $originalParams['offset'],
            $originalParams['count']
        );

        $payload = [
            '_meta' => [
                'method' => $result['method'],
                'url' => $result['url'],
                'status' => $result['status'],
                'query' => $result['query'],
                'body' => $result['body'],
                'received_at' => now()->toIso8601String(),
                'top_level_keys' => $result['top_level_keys'],
                'items_found' => $result['items_found'],
                'items_count' => $result['items_count'],
                'duration_ms' => $result['duration_ms'],
            ],
            'response' => $result['response'],
        ];

        PayloadCacheWriter::create(
            'probe_blocks_search',
            $externalId,
            PayloadCacheWriter::endpointFromUrl($result['url']),
            (int) $result['status'],
            $payload,
            $cityId,
            $lang,
        );

        $this->line('✓ Saved to ta_payload_cache (external_id: ' . $externalId . ')');
    }

    protected function sanitizeParams(array $params): array
    {
        $sanitized = [];
        foreach ($params as $key => $value) {
            if ($key === 'auth_token') {
                continue; // Skip auth_token completely
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
