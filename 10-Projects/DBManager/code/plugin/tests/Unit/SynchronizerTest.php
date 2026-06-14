<?php

namespace DBM\Tests\Unit;

use DBM\Sync\PayloadVerifier;
use DBM\Sync\Synchronizer;
use DBM\Tests\Support\FakeDataClient;
use DBM\Tests\Support\InMemoryCacheStore;
use PHPUnit\Framework\TestCase;

class SynchronizerTest extends TestCase
{
    private const SIGN = 'sign-secret';

    private function payloadBody(int $version): string
    {
        return json_encode(['site' => 'd.ua', 'version' => $version, 'values' => []]);
    }

    private function ok(int $version): array
    {
        $body = $this->payloadBody($version);

        return ['status' => 200, 'body' => $body, 'etag' => '"' . $version . '"',
                'signature' => hash_hmac('sha256', $body, self::SIGN)];
    }

    private function sync(FakeDataClient $client, InMemoryCacheStore $cache): Synchronizer
    {
        return new Synchronizer($client, $cache, new PayloadVerifier(),
            url: 'https://bridge/api/v1/data', token: 'tok', signingSecret: self::SIGN);
    }

    public function test_sync_stores_verified_payload(): void
    {
        $cache = new InMemoryCacheStore();
        $result = $this->sync(new FakeDataClient($this->ok(7)), $cache)->sync();

        $this->assertTrue($result->updated);
        $this->assertSame(7, $cache->version());
    }

    public function test_sync_rejects_bad_signature_and_keeps_cache(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->put(['site' => 'd.ua', 'version' => 3, 'values' => []]);
        $bad = $this->ok(9);
        $bad['signature'] = 'tampered';

        $result = $this->sync(new FakeDataClient($bad), $cache)->sync();

        $this->assertFalse($result->updated);
        $this->assertSame(3, $cache->version()); // кеш недоторканий
    }

    public function test_reconcile_sends_if_none_match_and_keeps_cache_on_304(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->put(['site' => 'd.ua', 'version' => 5, 'values' => []]);
        $client = new FakeDataClient(['status' => 304, 'body' => '', 'etag' => '"5"', 'signature' => '']);

        $result = $this->sync($client, $cache)->reconcile();

        $this->assertFalse($result->updated);
        $this->assertSame(5, $client->calls[0]['ifNoneMatchVersion']);
        $this->assertSame(5, $cache->version());
    }

    public function test_network_failure_keeps_cache(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->put(['site' => 'd.ua', 'version' => 2, 'values' => []]);
        $client = new FakeDataClient(['status' => 0, 'body' => '', 'etag' => '', 'signature' => '']);

        $result = $this->sync($client, $cache)->reconcile();

        $this->assertFalse($result->updated);
        $this->assertSame(2, $cache->version());
    }
}
