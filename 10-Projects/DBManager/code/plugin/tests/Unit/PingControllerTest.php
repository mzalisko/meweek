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

    public function test_different_site_accepts_lower_version(): void
    {
        // Перепідключення до іншого сайту: новий site_id з меншою версією має прийнятись —
        // монотонність версії діє ЛИШЕ в межах одного site_id. Так зміна токена перекидає
        // дані на інший сайт, навіть якщо в нового сайту менша версія.
        $cache = new InMemoryCacheStore();
        $cache->put(['site_id' => 2, 'version' => 67, 'values' => [['key' => 'old_site']]]);
        $controller = new PingController(new PayloadVerifier(), $cache, 'signing-secret');
        $signed = $this->signed(['site_id' => 1, 'version' => 4, 'values' => [['key' => 'new_site']]]);

        $this->assertSame(200, $controller->handle($signed['body'], $signed['signature'], $signed['timestamp']));
        $this->assertSame(4, $cache->version());
        $this->assertSame('new_site', $cache->get()['values'][0]['key']);
        $this->assertSame(1, (int) $cache->get()['site_id']);
    }

    public function test_same_site_lower_version_still_rejected(): void
    {
        // У межах одного site_id монотонність зберігається — захист від повтору старих даних.
        $cache = new InMemoryCacheStore();
        $cache->put(['site_id' => 1, 'version' => 6, 'values' => [['key' => 'keep']]]);
        $controller = new PingController(new PayloadVerifier(), $cache, 'signing-secret');
        $signed = $this->signed(['site_id' => 1, 'version' => 5, 'values' => [['key' => 'old']]]);

        $this->assertSame(409, $controller->handle($signed['body'], $signed['signature'], $signed['timestamp']));
        $this->assertSame(6, $cache->version());
    }
}
