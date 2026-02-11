<?php

namespace Tests\Unit\Domain\TrendAgent\Normalizers;

use App\Domain\TrendAgent\Normalizers\BlockNormalizer;
use PHPUnit\Framework\TestCase;

class BlockNormalizerTest extends TestCase
{
    public function test_normalize_returns_structure_with_required_id(): void
    {
        $payload = ['block_id' => 'b1', 'title' => 'Test'];
        $out = BlockNormalizer::normalize($payload);
        $this->assertIsArray($out);
        $this->assertSame('b1', $out['block_id']);
        $this->assertSame('Test', $out['title']);
    }

    public function test_normalize_uses_id_fallbacks(): void
    {
        $this->assertSame('b2', BlockNormalizer::normalize(['_id' => 'b2'])['block_id']);
        $this->assertSame('b3', BlockNormalizer::normalize(['id' => 'b3'])['block_id']);
    }

    public function test_normalize_returns_null_when_no_id(): void
    {
        $this->assertNull(BlockNormalizer::normalize(['title' => 'No ID']));
    }

    public function test_normalize_extracts_coordinates_from_geo(): void
    {
        $payload = [
            'block_id' => 'b1',
            'geo' => ['lat' => 55.75, 'lng' => 37.62],
        ];
        $out = BlockNormalizer::normalize($payload);
        $this->assertSame(55.75, $out['lat']);
        $this->assertSame(37.62, $out['lng']);
    }

    public function test_normalize_handles_missing_keys(): void
    {
        $out = BlockNormalizer::normalize(['block_id' => 'b1']);
        $this->assertNull($out['title']);
        $this->assertNull($out['min_price']);
    }

    public function test_normalize_extracts_images_array(): void
    {
        $payload = [
            'block_id' => 'b1',
            'images' => ['https://example.com/1.jpg', 'https://example.com/2.jpg'],
        ];
        $out = BlockNormalizer::normalize($payload);
        $this->assertIsArray($out['images']);
        $this->assertSame(['https://example.com/1.jpg', 'https://example.com/2.jpg'], $out['images']);
    }

    public function test_normalized_has_required_ui_keys_even_when_input_missing(): void
    {
        $out = BlockNormalizer::normalize(['block_id' => 'b1']);
        $this->assertArrayHasKey('id', $out);
        $this->assertSame('b1', $out['id']);
        $this->assertArrayHasKey('title', $out);
        $this->assertArrayHasKey('guid', $out);
        $this->assertArrayHasKey('city_id', $out);
        $this->assertArrayHasKey('lang', $out);
        $this->assertArrayHasKey('prices', $out);
        $this->assertIsArray($out['prices']);
        $this->assertArrayHasKey('min_price', $out['prices']);
        $this->assertArrayHasKey('price_from', $out['prices']);
        $this->assertArrayHasKey('coordinates', $out);
        $this->assertIsArray($out['coordinates']);
        $this->assertArrayHasKey('lat', $out['coordinates']);
        $this->assertArrayHasKey('lng', $out['coordinates']);
        $this->assertArrayHasKey('images', $out);
        $this->assertIsArray($out['images']);
    }
}
