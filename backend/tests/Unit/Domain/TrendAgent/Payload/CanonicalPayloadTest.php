<?php

namespace Tests\Unit\Domain\TrendAgent\Payload;

use App\Domain\TrendAgent\Payload\CanonicalPayload;
use PHPUnit\Framework\TestCase;

class CanonicalPayloadTest extends TestCase
{
    public function test_canonical_json_sorts_keys(): void
    {
        $data = ['z' => 1, 'a' => 2, 'm' => 3];
        $json = CanonicalPayload::canonicalJson($data);
        $this->assertSame('{"a":2,"m":3,"z":1}', $json);
    }

    public function test_payload_hash_stable_for_same_content(): void
    {
        $data = ['block_id' => 'b1', 'title' => 'Block 1', 'min_price' => 1000000];
        $hash1 = CanonicalPayload::payloadHash($data);
        $hash2 = CanonicalPayload::payloadHash(['min_price' => 1000000, 'title' => 'Block 1', 'block_id' => 'b1']);
        $this->assertSame($hash1, $hash2);
    }

    public function test_payload_hash_different_for_different_content(): void
    {
        $hash1 = CanonicalPayload::payloadHash(['id' => 'a']);
        $hash2 = CanonicalPayload::payloadHash(['id' => 'b']);
        $this->assertNotSame($hash1, $hash2);
    }

    public function test_canonical_json_normalizes_integer_floats(): void
    {
        $data = ['n' => 1.0];
        $json = CanonicalPayload::canonicalJson($data);
        $this->assertStringContainsString('"n":1', $json);
    }

    public function test_payload_hash_is_sha256_hex_length(): void
    {
        $hash = CanonicalPayload::payloadHash(['x' => 1]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }
}
