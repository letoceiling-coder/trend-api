<?php

namespace App\Domain\TrendAgent\Normalizers;

/**
 * Normalize apartment payload to a stable structure: scalars, numbers, price, status.
 */
final class ApartmentNormalizer
{
    /**
     * @param  array<string, mixed>  $payload  Raw apartment item from API
     * @return array<string, mixed>|null
     */
    public static function normalize(array $payload): ?array
    {
        $apartmentId = $payload['apartment_id'] ?? $payload['_id'] ?? $payload['id'] ?? null;
        if ($apartmentId === null) {
            return null;
        }

        $blockId = $payload['block_id'] ?? $payload['block'] ?? null;
        if (is_array($blockId)) {
            $blockId = $blockId['_id'] ?? $blockId['id'] ?? null;
        }
        $blockId = $blockId !== null ? (string) $blockId : null;

        $title = self::scalarString($payload['title'] ?? $payload['name'] ?? $payload['number'] ?? null);
        $guid = self::scalarString($payload['guid'] ?? $payload['slug'] ?? null);
        $rooms = isset($payload['rooms']) ? (int) $payload['rooms'] : null;
        $areaTotal = $payload['area_total'] ?? $payload['area'] ?? $payload['area_given'] ?? null;
        if ($areaTotal !== null && ! is_numeric($areaTotal)) {
            $areaTotal = null;
        }
        $areaTotal = $areaTotal !== null ? (float) $areaTotal : null;
        $floor = isset($payload['floor']) ? (int) $payload['floor'] : null;
        $price = isset($payload['price']) ? (int) $payload['price'] : (isset($payload['price_from']) ? (int) $payload['price_from'] : null);
        $statusRaw = $payload['status'] ?? null;
        $status = is_array($statusRaw) ? ($statusRaw['name'] ?? $statusRaw['name_short'] ?? null) : $statusRaw;
        $status = self::scalarString($status);
        $images = self::extractImages($payload);

        $out = [
            'apartment_id' => (string) $apartmentId,
            'block_id' => $blockId,
            'guid' => $guid,
            'title' => $title,
            'rooms' => $rooms,
            'area_total' => $areaTotal,
            'floor' => $floor,
            'price' => $price,
            'status' => $status,
            'images' => $images,
        ];

        return self::ensureApartmentMinimalKeys($out);
    }

    /**
     * UI-ready minimal contract: id, block_id, title, guid, city_id, lang, price, status, images[].
     * Missing keys get null or [].
     *
     * @param  array<string, mixed>  $n  Normalized apartment array
     * @return array<string, mixed>
     */
    public static function ensureApartmentMinimalKeys(array $n): array
    {
        $id = $n['apartment_id'] ?? $n['id'] ?? null;
        $images = $n['images'] ?? [];
        $images = is_array($images) ? $images : [];

        return array_merge([
            'id' => $id,
            'block_id' => $n['block_id'] ?? null,
            'title' => $n['title'] ?? null,
            'guid' => $n['guid'] ?? null,
            'city_id' => $n['city_id'] ?? null,
            'lang' => $n['lang'] ?? null,
            'price' => isset($n['price']) ? (int) $n['price'] : null,
            'status' => $n['status'] ?? null,
            'images' => array_values($images),
        ], $n);
    }

    /**
     * @return array<int, string>
     */
    private static function extractImages(array $payload): array
    {
        $sources = [
            $payload['images'] ?? null,
            $payload['gallery'] ?? null,
            $payload['photos'] ?? null,
            $payload['image_urls'] ?? null,
        ];
        $urls = [];
        foreach ($sources as $src) {
            if (is_array($src)) {
                foreach ($src as $item) {
                    if (is_string($item)) {
                        $urls[] = $item;
                    } elseif (is_array($item) && isset($item['url'])) {
                        $urls[] = (string) $item['url'];
                    } elseif (is_array($item) && isset($item['link'])) {
                        $urls[] = (string) $item['link'];
                    }
                }
            }
        }
        return array_values(array_unique($urls));
    }

    private static function scalarString(mixed $v): ?string
    {
        if ($v === null || is_scalar($v)) {
            return $v === null ? null : (string) $v;
        }
        return null;
    }
}
