<?php

namespace App\Domain\TrendAgent\Quality;

use App\Models\Domain\TrendAgent\TaApartment;
use App\Models\Domain\TrendAgent\TaApartmentDetail;
use App\Models\Domain\TrendAgent\TaBlock;
use App\Models\Domain\TrendAgent\TaBlockDetail;
use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use Illuminate\Support\Facades\Log;

class DataQualityRunner
{
    public const DEFAULT_CAP_PER_RUN = 500;

    /**
     * Run checks for the given scope; returns number of records written (capped by $cap).
     */
    public function runScope(string $scope, int $limit = 200, int $cap = self::DEFAULT_CAP_PER_RUN): int
    {
        return match ($scope) {
            'blocks' => $this->checkBlocks($limit, $cap),
            'apartments' => $this->checkApartments($limit, $cap),
            'block_detail' => $this->checkBlockDetail($limit, $cap),
            'apartment_detail' => $this->checkApartmentDetail($limit, $cap),
            'directories', 'unit_measurements' => 0,
            default => 0,
        };
    }

    /**
     * Blocks: block_id, title, city_id, lang required; prices >= 0; lat/lng valid if present.
     */
    public function checkBlocks(int $limit = 200, int $cap = self::DEFAULT_CAP_PER_RUN): int
    {
        $written = 0;
        $blocks = TaBlock::query()->orderByDesc('updated_at')->limit($limit)->get();

        foreach ($blocks as $block) {
            if ($written >= $cap) {
                break;
            }

            $cityId = $block->city_id;
            $lang = $block->lang;
            $entityId = $block->block_id;

            if (empty($block->block_id)) {
                $written += $this->record('blocks', $entityId, $cityId, $lang, 'required_block_id', 'fail', 'block_id is missing', []) ? 1 : 0;
            }
            if ($block->title === null || $block->title === '') {
                $written += $this->record('blocks', $entityId, $cityId, $lang, 'required_title', 'fail', 'title is missing', []) ? 1 : 0;
            }
            if (empty($block->city_id)) {
                $written += $this->record('blocks', $entityId, $cityId, $lang, 'required_city_id', 'fail', 'city_id is missing', []) ? 1 : 0;
            }
            if (empty($block->lang)) {
                $written += $this->record('blocks', $entityId, $cityId, $lang, 'required_lang', 'fail', 'lang is missing', []) ? 1 : 0;
            }

            if ($block->min_price !== null && (int) $block->min_price < 0) {
                $written += $this->record('blocks', $entityId, $cityId, $lang, 'min_price_negative', 'fail', 'min_price must be >= 0', ['min_price' => (int) $block->min_price]) ? 1 : 0;
            }
            if ($block->max_price !== null && (int) $block->max_price < 0) {
                $written += $this->record('blocks', $entityId, $cityId, $lang, 'max_price_negative', 'fail', 'max_price must be >= 0', ['max_price' => (int) $block->max_price]) ? 1 : 0;
            }

            $lat = $block->lat !== null ? (float) $block->lat : null;
            $lng = $block->lng !== null ? (float) $block->lng : null;
            if ($lat !== null && ($lat < -90 || $lat > 90)) {
                $written += $this->record('blocks', $entityId, $cityId, $lang, 'lat_invalid', 'fail', 'lat must be in [-90, 90]', ['lat' => $lat]) ? 1 : 0;
            }
            if ($lng !== null && ($lng < -180 || $lng > 180)) {
                $written += $this->record('blocks', $entityId, $cityId, $lang, 'lng_invalid', 'fail', 'lng must be in [-180, 180]', ['lng' => $lng]) ? 1 : 0;
            }

            if ($written < $cap && $block->block_id && $block->title !== null && $block->city_id && $block->lang
                && ($block->min_price === null || (int) $block->min_price >= 0)
                && ($block->max_price === null || (int) $block->max_price >= 0)
                && ($lat === null || ($lat >= -90 && $lat <= 90))
                && ($lng === null || ($lng >= -180 && $lng <= 180))) {
                $written += $this->record('blocks', $entityId, $cityId, $lang, 'required_fields_and_prices', 'pass', 'OK', []) ? 1 : 0;
            }
        }

        return $written;
    }

    /**
     * Apartments: apartment_id, price >= 0, title scalar.
     */
    public function checkApartments(int $limit = 200, int $cap = self::DEFAULT_CAP_PER_RUN): int
    {
        $written = 0;
        $apartments = TaApartment::query()->orderByDesc('updated_at')->limit($limit)->get();

        foreach ($apartments as $apt) {
            if ($written >= $cap) {
                break;
            }

            $entityId = $apt->apartment_id;
            $cityId = $apt->city_id;
            $lang = $apt->lang;

            if (empty($apt->apartment_id)) {
                $written += $this->record('apartments', $entityId, $cityId, $lang, 'required_apartment_id', 'fail', 'apartment_id is missing', []) ? 1 : 0;
            }
            if ($apt->price !== null && (int) $apt->price < 0) {
                $written += $this->record('apartments', $entityId, $cityId, $lang, 'price_negative', 'fail', 'price must be >= 0', ['price' => (int) $apt->price]) ? 1 : 0;
            }
            if ($apt->title !== null && ! is_scalar($apt->title)) {
                $written += $this->record('apartments', $entityId, $cityId, $lang, 'title_not_scalar', 'warn', 'title should be scalar', []) ? 1 : 0;
            }

            if ($written < $cap && $apt->apartment_id && ($apt->price === null || (int) $apt->price >= 0) && ($apt->title === null || is_scalar($apt->title))) {
                $written += $this->record('apartments', $entityId, $cityId, $lang, 'required_and_price', 'pass', 'OK', []) ? 1 : 0;
            }
        }

        return $written;
    }

    /**
     * Block detail: unified_payload present, fetched_at not null.
     */
    public function checkBlockDetail(int $limit = 200, int $cap = self::DEFAULT_CAP_PER_RUN): int
    {
        $written = 0;
        $rows = TaBlockDetail::query()->orderByDesc('updated_at')->limit($limit)->get();

        foreach ($rows as $row) {
            if ($written >= $cap) {
                break;
            }

            $entityId = $row->block_id;
            $cityId = $row->city_id;
            $lang = $row->lang;

            $hasUnified = $row->unified_payload !== null && (is_array($row->unified_payload) || (is_string($row->unified_payload) && $row->unified_payload !== ''));
            if (! $hasUnified) {
                $written += $this->record('block_detail', $entityId, $cityId, $lang, 'unified_payload_missing', 'fail', 'unified_payload is missing or empty', []) ? 1 : 0;
            }
            if ($row->fetched_at === null) {
                $written += $this->record('block_detail', $entityId, $cityId, $lang, 'fetched_at_null', 'fail', 'fetched_at is null', []) ? 1 : 0;
            }
            if ($written < $cap && $hasUnified && $row->fetched_at !== null) {
                $written += $this->record('block_detail', $entityId, $cityId, $lang, 'unified_and_fetched', 'pass', 'OK', []) ? 1 : 0;
            }
        }

        return $written;
    }

    /**
     * Apartment detail: unified_payload present; prices_* may be null; fetched_at present.
     */
    public function checkApartmentDetail(int $limit = 200, int $cap = self::DEFAULT_CAP_PER_RUN): int
    {
        $written = 0;
        $rows = TaApartmentDetail::query()->orderByDesc('updated_at')->limit($limit)->get();

        foreach ($rows as $row) {
            if ($written >= $cap) {
                break;
            }

            $entityId = $row->apartment_id;
            $cityId = $row->city_id;
            $lang = $row->lang;

            $hasUnified = $row->unified_payload !== null && (is_array($row->unified_payload) || (is_string($row->unified_payload) && $row->unified_payload !== ''));
            if (! $hasUnified) {
                $written += $this->record('apartment_detail', $entityId, $cityId, $lang, 'unified_payload_missing', 'fail', 'unified_payload is missing or empty', []) ? 1 : 0;
            }
            if ($row->fetched_at === null) {
                $written += $this->record('apartment_detail', $entityId, $cityId, $lang, 'fetched_at_null', 'fail', 'fetched_at is null', []) ? 1 : 0;
            }
            if ($written < $cap && $hasUnified && $row->fetched_at !== null) {
                $written += $this->record('apartment_detail', $entityId, $cityId, $lang, 'unified_and_fetched', 'pass', 'OK', []) ? 1 : 0;
            }
        }

        return $written;
    }

    /**
     * Write one check record. Message and context must be sanitized (no secrets).
     * Returns true if record was created.
     */
    protected function record(
        string $scope,
        ?string $entityId,
        ?string $cityId,
        ?string $lang,
        string $checkName,
        string $status,
        string $message,
        array $context
    ): bool {
        if (! in_array($status, [TaDataQualityCheck::STATUS_PASS, TaDataQualityCheck::STATUS_WARN, TaDataQualityCheck::STATUS_FAIL], true)) {
            return false;
        }
        $message = $this->sanitize($message, 1024);
        $context = $this->sanitizeContext($context);

        try {
            TaDataQualityCheck::create([
                'scope' => $scope,
                'entity_id' => $entityId,
                'city_id' => $cityId,
                'lang' => $lang,
                'check_name' => $checkName,
                'status' => $status,
                'message' => $message,
                'context' => $context,
                'created_at' => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            Log::warning('TrendAgent DataQualityRunner record failed', [
                'scope' => $scope,
                'check_name' => $checkName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function sanitize(string $value, int $maxLen): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        if (strlen($value) > $maxLen) {
            return substr($value, 0, $maxLen - 3) . '...';
        }
        return $value;
    }

    private function sanitizeContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            if (is_string($k) && preg_match('/^(token|password|secret|key|auth)/i', $k)) {
                continue;
            }
            $out[$k] = is_scalar($v) ? $v : json_encode($v);
        }
        return $out;
    }
}
