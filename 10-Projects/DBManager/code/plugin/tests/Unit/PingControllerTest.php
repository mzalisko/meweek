<?php

namespace DBM\Tests\Unit;

use DBM\Rest\PingController;
use DBM\Sync\PayloadVerifier;
use DBM\Tests\Support\InMemoryCacheStore;
use PHPUnit\Framework\TestCase;

class PingControllerTest extends TestCase
{
    private function signed(array $payload, string $secret = 'signing-secret', ?int $timestamp = null): array
    {
        $body = json_encode($payload);
        $timestamp = $timestamp ?? time();

        return [
            'body' => $body,
            'timestamp' => (string) $timestamp,
            'signature' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ];
    }

    public function test_valid_payload_updates_cache(): void
    {
        $signed = $this->signed(['version' => 6, 'values' => []]);
        $cache = new InMemoryCacheStore();

        $controller = new PingController(new PayloadVerifier(), $cache, 'signing-secret');
        $status = $controller->handle($signed['body'], $signed['signature'], $signed['timestamp']);

        $this->assertSame(200, $status);
        $this->assertSame(6, $cache->version());
    }

    public function test_invalid_signature_is_rejected_without_cache_update(): void
    {
        $cache = new InMemoryCacheStore();
        $controller = new PingController(new PayloadVerifier(), $cache, 'signing-secret');
        $signed = $this->signed(['version' => 6, 'values' => []]);

        $this->assertSame(401, $controller->handle($signed['body'], 'wrong', $signed['timestamp']));
        $this->assertSame(0, $cache->version());
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        $cache = new InMemoryCacheStore();
        $controller = new PingController(new PayloadVerifier(), $cache, 'signing-secret');
        $signed = $this->signed(['version' => 6, 'values' => []], timestamp: time() - 301);

        $this->assertSame(401, $controller->handle($signed['body'], $signed['signature'], $signed['timestamp']));
        $this->assertSame(0, $cache->version());
    }

    public function test_stale_payload_does_not_replace_cache(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->put(['version' => 6, 'values' => [['key' => 'keep']]]);
        $controller = new PingController(new PayloadVerifier(), $cache, 'signing-secret');
        $signed = $this->signed(['version' => 5, 'values' => [['key' => 'old']]]);

        $this->assertSame(409, $controller->handle($signed['body'], $signed['signature'], $signed['timestamp']));
        $this->assertSame(6, $cache->version());
        $this->assertSame('keep', $cache->get()['values'][0]['key']);
    }

    public function test_equal_payload_version_is_idempotent(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->put(['version' => 6, 'values' => [['key' => 'keep']]]);
        $controller = new PingController(new PayloadVerifier(), $cache, 'signing-secret');
        $signed = $this->signed(['version' => 6, 'values' => [['key' => 'same-version']]]);

        $this->assertSame(200, $controller->handle($signed['body'], $signed['signature'], $signed['timestamp']));
        $this->assertSame('keep', $cache->get()['values'][0]['key']);
    }
}
