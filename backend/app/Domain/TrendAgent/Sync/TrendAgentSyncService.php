<?php

namespace App\Domain\TrendAgent\Sync;

use App\Domain\TrendAgent\Sync\SyncRunner;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use App\Models\Domain\TrendAgent\TaBlock;
use App\Models\Domain\TrendAgent\TaBlockDetail;
use App\Models\Domain\TrendAgent\TaDirectory;
use App\Models\Domain\TrendAgent\TaPayloadCache;
use App\Models\Domain\TrendAgent\TaSyncRun;
use App\Models\Domain\TrendAgent\TaUnitMeasurement;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TrendAgentSyncService
{
    public function __construct(
        protected TrendHttpClient $http,
        protected SyncRunner $syncRunner
    ) {
    }

    /**
     * Sync unit measurements from core API
     */
    public function syncUnitMeasurements(string $cityId, string $lang = 'ru', bool $storeRawPayload = true): TaSyncRun
    {
        $run = $this->syncRunner->startRun('unit_measurements', $cityId, $lang);

        try {
            $coreApi = config('trendagent.api.core', 'https://api.trendagent.ru');
            $url = rtrim($coreApi, '/') . '/v4_29/unit_measurements';

            $response = $this->http->get($url, [
                'city' => $cityId,
                'lang' => $lang,
            ]);

            $data = $response->json();
            $items = $data ?? [];

            if (! is_array($items)) {
                $items = [];
            }

            $itemsFetched = count($items);
            $itemsSaved = 0;

            DB::transaction(function () use ($items, $cityId, $lang, $storeRawPayload, &$itemsSaved) {
                foreach ($items as $item) {
                    if (! is_array($item) || ! isset($item['_id'])) {
                        continue;
                    }

                    TaUnitMeasurement::updateOrCreate(
                        ['id' => $item['_id']],
                        [
                            'name' => $item['name'] ?? null,
                            'code' => $item['code'] ?? null,
                            'currency' => $item['currency'] ?? null,
                            'measurement' => $item['measurement'] ?? null,
                            'raw' => $item,
                        ]
                    );

                    $itemsSaved++;

                    if ($storeRawPayload) {
                        TaPayloadCache::create([
                            'provider' => 'trendagent',
                            'scope' => 'unit_measurements',
                            'external_id' => $item['_id'],
                            'city_id' => $cityId,
                            'lang' => $lang,
                            'payload' => json_encode($item),
                            'fetched_at' => now(),
                        ]);
                    }
                }
            });

            return $this->syncRunner->finishSuccess($run, $itemsFetched, $itemsSaved);
        } catch (Throwable $e) {
            return $this->syncRunner->finishFail($run, $e, [
                'endpoint' => '/v4_29/unit_measurements',
                'city_id' => $cityId,
                'lang' => $lang,
            ]);
        }
    }

    /**
     * Sync directories from apartment API
     *
     * @param  array  $types  Directory types to sync (e.g. ['rooms', 'deadlines', 'regions'])
     */
    public function syncDirectories(array $types, string $cityId, string $lang = 'ru', bool $storeRawPayload = true): TaSyncRun
    {
        $run = $this->syncRunner->startRun('directories', $cityId, $lang);

        try {
            $apartmentApi = config('trendagent.api.apartment', 'https://apartment-api.trendagent.ru');
            $url = rtrim($apartmentApi, '/') . '/v1/directories';

            $queryParams = ['city' => $cityId, 'lang' => $lang];
            foreach ($types as $type) {
                $queryParams['types'][] = $type;
            }

            $response = $this->http->get($url, $queryParams);

            $data = $response->json();

            if (! is_array($data)) {
                $data = [];
            }

            $itemsFetched = count($data);
            $itemsSaved = 0;

            DB::transaction(function () use ($data, $cityId, $lang, $storeRawPayload, &$itemsSaved) {
                foreach ($data as $type => $payload) {
                    if (! is_string($type) || ! is_array($payload)) {
                        continue;
                    }

                    TaDirectory::updateOrCreate(
                        [
                            'type' => $type,
                            'city_id' => $cityId,
                            'lang' => $lang,
                        ],
                        [
                            'payload' => $payload,
                            'fetched_at' => now(),
                        ]
                    );

                    $itemsSaved++;

                    if ($storeRawPayload) {
                        TaPayloadCache::create([
                            'provider' => 'trendagent',
                            'scope' => 'directories',
                            'external_id' => $type,
                            'city_id' => $cityId,
                            'lang' => $lang,
                            'payload' => json_encode($payload),
                            'fetched_at' => now(),
                        ]);
                    }
                }
            });

            return $this->syncRunner->finishSuccess($run, $itemsFetched, $itemsSaved);
        } catch (Throwable $e) {
            return $this->syncRunner->finishFail($run, $e, [
                'endpoint' => '/v1/directories',
                'types' => $types,
                'city_id' => $cityId,
                'lang' => $lang,
            ]);
        }
    }

    /**
     * Sync blocks from blocks/search endpoint with pagination
     *
     * @param  array  $params  Additional query params (filters, etc.)
     * @return array ['run' => TaSyncRun, 'total_pages' => int]
     */
    public function syncBlocksSearch(array $params = [], ?string $cityId = null, ?string $lang = null, bool $storeRawPayload = true): array
    {
        $cityId = $cityId ?? Config::get('trendagent.default_city_id');
        $lang = $lang ?? Config::get('trendagent.default_lang', 'ru');

        if (! $cityId) {
            throw new RuntimeException('City ID is required for syncBlocksSearch');
        }

        $run = $this->syncRunner->startRun('blocks_search', $cityId, $lang);

        try {
            $coreApi = config('trendagent.api.core', 'https://api.trendagent.ru');
            $url = rtrim($coreApi, '/') . '/v4_29/blocks/search';

            // Default params
            $defaultParams = [
                'show_type' => 'list',
                'count' => 20,
                'offset' => 0,
                'sort' => 'price',
                'sort_order' => 'asc',
            ];

            $queryParams = array_merge($defaultParams, $params, [
                'city' => $cityId,
                'lang' => $lang,
            ]);

            $totalFetched = 0;
            $totalSaved = 0;
            $currentOffset = (int) $queryParams['offset'];
            $count = (int) $queryParams['count'];
            $maxPages = $params['max_pages'] ?? 50;
            $pagesProcessed = 0;

            while ($pagesProcessed < $maxPages) {
                $queryParams['offset'] = $currentOffset;

                $response = $this->http->get($url, $queryParams);
                $data = $response->json();

                // Shape detector: find array of blocks
                $blocks = $this->detectBlocksArray($data);

                if ($blocks === null) {
                    throw new RuntimeException(
                        'Unable to detect blocks array in response. Response keys: ' . implode(', ', array_keys($data ?? []))
                    );
                }

                $pageFetched = count($blocks);
                $totalFetched += $pageFetched;

                if ($pageFetched > 0) {
                    $pageSaved = $this->saveBlocks($blocks, $cityId, $lang);
                    $totalSaved += $pageSaved;
                }

                // Store raw payload cache for this page
                if ($storeRawPayload) {
                    $externalId = sprintf(
                        'show_type:%s;offset:%d',
                        $queryParams['show_type'],
                        $currentOffset
                    );

                    TaPayloadCache::create([
                        'provider' => 'trendagent',
                        'scope' => 'blocks_search_page',
                        'external_id' => $externalId,
                        'city_id' => $cityId,
                        'lang' => $lang,
                        'payload' => json_encode($data),
                        'fetched_at' => now(),
                    ]);
                }

                $pagesProcessed++;

                // Stop if we got fewer items than requested (last page)
                if ($pageFetched < $count) {
                    break;
                }

                $currentOffset += $count;
            }

            $this->syncRunner->finishSuccess($run, $totalFetched, $totalSaved);

            return [
                'run' => $run->fresh(),
                'total_pages' => $pagesProcessed,
            ];
        } catch (Throwable $e) {
            $this->syncRunner->finishFail($run, $e, [
                'endpoint' => '/v4_29/blocks/search',
                'city_id' => $cityId,
                'lang' => $lang,
                'params' => $params,
            ]);

            return [
                'run' => $run->fresh(),
                'total_pages' => 0,
            ];
        }
    }

    /**
     * Detect array of blocks in response. Shape detector.
     *
     * @return array|null Array of blocks or null if not found
     */
    protected function detectBlocksArray(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        // Direct array of blocks
        if ($this->looksLikeBlocksArray($data)) {
            return $data;
        }

        // Common patterns (ordered by real API observations)
        $pathsToCheck = [
            ['data', 'results'],  // Real TrendAgent API structure
            ['items'],
            ['data', 'items'],
            ['result', 'items'],
            ['results'],
            ['blocks'],
            ['data', 'blocks'],
            ['result', 'blocks'],
        ];

        foreach ($pathsToCheck as $path) {
            $value = $this->getNestedValue($data, $path);
            if (is_array($value) && $this->looksLikeBlocksArray($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Check if array looks like an array of blocks
     */
    protected function looksLikeBlocksArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }

        // Check first element
        $first = reset($arr);
        if (! is_array($first)) {
            return false;
        }

        // Must have at least one of these IDs
        return isset($first['block_id']) || isset($first['_id']) || isset($first['id']);
    }

    /**
     * Get nested value from array by path
     */
    protected function getNestedValue(array $data, array $path): mixed
    {
        $current = $data;
        foreach ($path as $key) {
            if (! is_array($current) || ! isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }

    /**
     * Save blocks to database with upsert
     *
     * @return int Number of blocks saved
     */
    protected function saveBlocks(array $blocks, string $cityId, string $lang): int
    {
        $saved = 0;

        DB::transaction(function () use ($blocks, $cityId, $lang, &$saved) {
            foreach ($blocks as $block) {
                if (! is_array($block)) {
                    continue;
                }

                $blockId = $block['block_id'] ?? $block['_id'] ?? $block['id'] ?? null;
                if (! $blockId) {
                    continue;
                }

                // Extract fields with fallbacks
                $guid = $block['guid'] ?? $block['slug'] ?? null;
                $title = $block['title'] ?? $block['name'] ?? null;
                $kind = $block['kind'] ?? $block['type'] ?? null;
                $status = $block['status'] ?? null;

                // Prices
                $minPrice = $block['min_price'] ?? $block['price_from'] ?? $block['lowest_price'] ?? null;
                $maxPrice = $block['max_price'] ?? $block['price_to'] ?? $block['highest_price'] ?? null;

                $deadline = $block['deadline'] ?? null;
                $developerName = $block['developer_name'] ?? $block['developer'] ?? null;

                // Coordinates
                $lat = null;
                $lng = null;
                if (isset($block['lat']) && isset($block['lng'])) {
                    $lat = $block['lat'];
                    $lng = $block['lng'];
                } elseif (isset($block['geo']['lat']) && isset($block['geo']['lng'])) {
                    $lat = $block['geo']['lat'];
                    $lng = $block['geo']['lng'];
                }

                TaBlock::updateOrCreate(
                    ['block_id' => (string) $blockId],
                    [
                        'guid' => $guid,
                        'title' => $title,
                        'city_id' => $cityId,
                        'lang' => $lang,
                        'kind' => $kind,
                        'status' => $status,
                        'min_price' => $minPrice ? (int) $minPrice : null,
                        'max_price' => $maxPrice ? (int) $maxPrice : null,
                        'deadline' => $deadline,
                        'developer_name' => $developerName,
                        'lat' => $lat,
                        'lng' => $lng,
                        'raw' => $block,
                        'fetched_at' => now(),
                    ]
                );

                $saved++;
            }
        });

        return $saved;
    }

    /**
     * Sync block detail information (unified, advantages, nearby_places, bank, geo_buildings, apartments_min_price)
     */
    public function syncBlockDetail(string $blockId, ?string $cityId = null, ?string $lang = null, bool $storeRawPayload = true): TaSyncRun
    {
        $cityId = $cityId ?? Config::get('trendagent.default_city_id');
        $lang = $lang ?? Config::get('trendagent.default_lang', 'ru');

        if (! $cityId) {
            throw new RuntimeException('City ID is required for syncBlockDetail');
        }

        $run = $this->syncRunner->startRun('block_detail:' . $blockId, $cityId, $lang);

        try {
            $coreApi = config('trendagent.api.core', 'https://api.trendagent.ru');
            $baseUrl = rtrim($coreApi, '/');

            $endpoints = [
                'unified' => [
                    'path' => "/v4_29/blocks/{$blockId}/unified/",
                    'query' => ['formating' => 'true', 'ch' => 'false'],
                    'required' => true,
                ],
                'advantages' => [
                    'path' => "/v4_29/blocks/{$blockId}/advantages/",
                    'query' => [],
                    'required' => false,
                ],
                'nearby_places' => [
                    'path' => "/v4_29/blocks/{$blockId}/nearby_places/",
                    'query' => [],
                    'required' => false,
                ],
                'bank' => [
                    'path' => "/v4_29/blocks/{$blockId}/bank/",
                    'query' => [],
                    'required' => false,
                ],
                'geo_buildings' => [
                    'path' => "/v4_29/blocks/{$blockId}/geo/buildings/",
                    'query' => [],
                    'required' => false,
                ],
                'apartments_min_price' => [
                    'path' => "/v4_29/blocks/{$blockId}/apartments/min-price/",
                    'query' => [],
                    'required' => false,
                ],
            ];

            $payloads = [];
            $fetchedCount = 0;

            foreach ($endpoints as $key => $config) {
                $url = $baseUrl . $config['path'];
                $queryParams = array_merge($config['query'], ['city' => $cityId, 'lang' => $lang]);

                try {
                    $response = $this->http->get($url, $queryParams);

                    if ($response->status() >= 200 && $response->status() < 300) {
                        $payloads[$key . '_payload'] = $response->json();
                        $fetchedCount++;

                        if ($storeRawPayload) {
                            $this->saveSinglePayloadToCache($blockId, $key, $response->json(), $url, $cityId, $lang);
                        }
                    } elseif ($config['required']) {
                        throw new RuntimeException("Required endpoint {$key} failed with status {$response->status()}");
                    }
                } catch (Throwable $e) {
                    if ($config['required']) {
                        throw $e;
                    }
                    // Optional endpoint failed, continue
                }
            }

            // Save to ta_block_details
            DB::transaction(function () use ($blockId, $cityId, $lang, $payloads) {
                TaBlockDetail::updateOrCreate(
                    ['block_id' => $blockId, 'city_id' => $cityId, 'lang' => $lang],
                    array_merge($payloads, ['fetched_at' => now()])
                );
            });

            return $this->syncRunner->finishSuccess($run, $fetchedCount, 1);
        } catch (Throwable $e) {
            return $this->syncRunner->finishFail($run, $e, [
                'block_id' => $blockId,
                'city_id' => $cityId,
                'lang' => $lang,
            ]);
        }
    }

    /**
     * Save single payload to cache
     */
    protected function saveSinglePayloadToCache(string $blockId, string $key, mixed $data, string $url, string $cityId, string $lang): void
    {
        $externalId = $blockId . ':' . $key;

        $payload = [
            '_meta' => [
                'endpoint' => $key,
                'url' => $url,
                'received_at' => now()->toIso8601String(),
                'top_level_keys' => is_array($data) ? array_keys($data) : [],
            ],
            'response' => $data,
        ];

        TaPayloadCache::create([
            'provider' => 'trendagent',
            'scope' => 'block_detail',
            'external_id' => $externalId,
            'city_id' => $cityId,
            'lang' => $lang,
            'payload' => json_encode($payload),
            'fetched_at' => now(),
        ]);
    }
}
