<?php

namespace DBM\Tests\Unit;

use DBM\Geo\GeoDbSynchronizer;
use DBM\Sync\PayloadVerifier;
use DBM\Tests\Support\FakeGeoDbClient;
use DBM\Tests\Support\InMemoryGeoDbStore;
use PHPUnit\Framework\TestCase;

class GeoDbSynchronizerTest extends TestCase
{
    private const SIGN = 'sign';

    private function ok(string $bytes): array
    {
        return ['status' => 200, 'body' => $bytes, 'etag' => '"' . hash('sha256', $bytes) . '"',
                'signature' => hash_hmac('sha256', $bytes, self::SIGN)];
    }

    private function sync(FakeGeoDbClient $c, InMemoryGeoDbStore $s): GeoDbSynchronizer
    {
        return new GeoDbSynchronizer($c, $s, new PayloadVerifier(),
            url: 'https://bridge/api/v1/geodb', token: 'tok', signingSecret: self::SIGN);
    }

    public function test_downloads_and_stores_verified_database(): void
    {
        $store = new InMemoryGeoDbStore();
        $bytes = random_bytes(20);

        $this->assertTrue($this->sync(new FakeGeoDbClient($this->ok($bytes)), $store)->sync());
        $this->assertSame($bytes, $store->bytes());
    }

    public function test_bad_signature_keeps_existing(): void
    {
        $store = new InMemoryGeoDbStore();
        $store->put('OLD');
        $bad = $this->ok(random_bytes(8));
        $bad['signature'] = 'tampered';

        $this->assertFalse($this->sync(new FakeGeoDbClient($bad), $store)->sync());
        $this->assertSame('OLD', $store->bytes());
    }

    public function test_304_is_noop(): void
    {
        $store = new InMemoryGeoDbStore();
        $store->put('KEEP');
        $client = new FakeGeoDbClient(['status' => 304, 'body' => '', 'etag' => '', 'signature' => '']);

        $this->assertFalse($this->sync($client, $store)->sync());
        $this->assertSame('KEEP', $store->bytes());
    }
}
