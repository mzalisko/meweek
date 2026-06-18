<?php

namespace DBM\Tests\Unit;

use DBM\Rest\PingController;
use DBM\Sync\PayloadVerifier;
use DBM\Tests\Support\InMemoryCacheStore;
use PHPUnit\Framework\TestCase;

class PingControllerTest extends TestCase
{
    public function test_valid_payload_updates_cache(): void
    {
        $payload = ['version' => 6, 'values' => []];
        $body = json_encode($payload);
        $sig = hash_hmac('sha256', $body, 'signing-secret');
        $cache = new InMemoryCacheStore();

        $controller = new PingController(new PayloadVerifier(), $cache, 'signing-secret');
        $status = $controller->handle($body, $sig);

        $this->assertSame(200, $status);
        $this->assertSame(6, $cache->version());
    }

    public function test_invalid_signature_is_rejected_without_cache_update(): void
    {
        $cache = new InMemoryCacheStore();
        $controller = new PingController(new PayloadVerifier(), $cache, 'signing-secret');

        $this->assertSame(401, $controller->handle('{"version":6,"values":[]}', 'wrong'));
        $this->assertSame(0, $cache->version());
    }
}
