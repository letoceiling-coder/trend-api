<?php

namespace Tests\Unit\Domain\TrendAgent\Normalizers;

use App\Domain\TrendAgent\Normalizers\ApartmentNormalizer;
use PHPUnit\Framework\TestCase;

class ApartmentNormalizerTest extends TestCase
{
    public function test_normalize_returns_structure_with_apartment_id(): void
    {
        $payload = ['apartment_id' => 'a1', 'title' => 'Apt 1', 'price' => 5000000];
        $out = ApartmentNormalizer::normalize($payload);
        $this->assertIsArray($out);
        $this->assertSame('a1', $out['apartment_id']);
        $this->assertSame('Apt 1', $out['title']);
        $this->assertSame(5000000, $out['price']);
    }

    public function test_normalize_returns_null_when_no_id(): void
    {
        $this->assertNull(ApartmentNormalizer::normalize(['title' => 'No ID']));
    }

    public function test_normalize_extracts_block_id_from_object(): void
    {
        $payload = [
            'apartment_id' => 'a1',
            'block' => ['_id' => 'block-1'],
        ];
        $out = ApartmentNormalizer::normalize($payload);
        $this->assertSame('block-1', $out['block_id']);
    }

    public function test_normalized_has_required_ui_keys_even_when_input_missing(): void
    {
        $out = ApartmentNormalizer::normalize(['apartment_id' => 'a1']);
        $this->assertArrayHasKey('id', $out);
        $this->assertSame('a1', $out['id']);
        $this->assertArrayHasKey('block_id', $out);
        $this->assertArrayHasKey('title', $out);
        $this->assertArrayHasKey('guid', $out);
        $this->assertArrayHasKey('city_id', $out);
        $this->assertArrayHasKey('lang', $out);
        $this->assertArrayHasKey('price', $out);
        $this->assertArrayHasKey('status', $out);
        $this->assertArrayHasKey('images', $out);
        $this->assertIsArray($out['images']);
    }
}
