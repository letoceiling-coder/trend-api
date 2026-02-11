<?php

namespace App\Domain\TrendAgent\Normalizers;

/**
 * Normalize block payload to a stable structure: scalars, numbers, coordinates, prices, status.
 */
final class BlockNormalizer
{
    /**
     * @param  array<string, mixed>  $payload  Raw block item from API
     * @return array<string, mixed>|null  Normalized structure or null if unexpected shape
     */
    public static function normalize(array $payload): ?array
    {
        $id = $payload['block_id'] ?? $payload['_id'] ?? $payload['id'] ?? null;
        if ($id === null) {
            return null;
        }

        $guid = $payload['guid'] ?? $payload['slug'] ?? null;
        $title = $payload['title'] ?? $payload['name'] ?? null;
        $kind = $payload['kind'] ?? $payload['type'] ?? null;
        $status = $payload['status'] ?? null;
        if (is_array($status)) {
            $status = $status['name'] ?? $status['name_short'] ?? null;
        }
        $status = self::scalarString($status);

        $minPrice = $payload['min_price'] ?? $payload['price_from'] ?? $payload['lowest_price'] ?? null;
        $maxPrice = $payload['max_price'] ?? $payload['price_to'] ?? $payload['highest_price'] ?? null;
        $deadline = self::scalarString($payload['deadline'] ?? null);
        $developerName = self::scalarString($payload['developer_name'] ?? $payload['developer'] ?? null);

        $lat = null;
        $lng = null;
        if (isset($payload['lat']) && isset($payload['lng'])) {
            $lat = self::float($payload['lat']);
            $lng = self::float($payload['lng']);
        } elseif (isset($payload['geo']['lat']) && isset($payload['geo']['lng'])) {
            $lat = self::float($payload['geo']['lat']);
            $lng = self::float($payload['geo']['lng']);
        }

        $images = self::extractImages($payload);

        $out = [
            'block_id' => (string) $id,
            'guid' => self::scalarString($guid),
            'title' => self::scalarString($title),
            'kind' => self::scalarString($kind),
            'status' => $status,
            'min_price' => $minPrice !== null ? (int) $minPrice : null,
            'max_price' => $maxPrice !== null ? (int) $maxPrice : null,
            'deadline' => $deadline,
            'developer_name' => $developerName,
            'lat' => $lat,
            'lng' => $lng,
            'images' => $images,
        ];

        return self::ensureBlockMinimalKeys($out);
    }

    /**
     * UI-ready minimal contract: id, title, guid, city_id, lang, prices, coordinates, images[].
     * Missing keys get null or [].
     *
     * @param  array<string, mixed>  $n  Normalized block array
     * @return array<string, mixed>
     */
    public static function ensureBlockMinimalKeys(array $n): array
    {
        $id = $n['block_id'] ?? $n['id'] ?? null;
        $minPrice = $n['min_price'] ?? null;
        $priceFrom = $n['price_from'] ?? $minPrice;
        $lat = $n['lat'] ?? null;
        $lng = $n['lng'] ?? null;
        $images = $n['images'] ?? [];
        $images = is_array($images) ? $images : [];

        return array_merge([
            'id' => $id,
            'title' => $n['title'] ?? null,
            'guid' => $n['guid'] ?? null,
            'city_id' => $n['city_id'] ?? null,
            'lang' => $n['lang'] ?? null,
            'prices' => [
                'min_price' => $minPrice !== null ? (int) $minPrice : null,
                'price_from' => $priceFrom !== null ? (int) $priceFrom : null,
            ],
            'coordinates' => [
                'lat' => $lat !== null ? (float) $lat : null,
                'lng' => $lng !== null ? (float) $lng : null,
            ],
            'images' => array_values($images),
        ], $n);
    }

    /**
     * Extract image URLs as strict string array from payload (images, gallery, photos, etc.).
     *
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

    private static function float(mixed $v): ?float
    {
        if ($v === null) {
            return null;
        }
        if (is_numeric($v)) {
            return (float) $v;
        }
        return null;
    }
}
