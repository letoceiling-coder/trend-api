<?php

namespace App\Domain\TrendAgent\Sync;

use App\Domain\TrendAgent\Sync\SyncRunner;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use App\Models\Domain\TrendAgent\TaDirectory;
use App\Models\Domain\TrendAgent\TaPayloadCache;
use App\Models\Domain\TrendAgent\TaSyncRun;
use App\Models\Domain\TrendAgent\TaUnitMeasurement;
use Illuminate\Support\Facades\DB;
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
}
