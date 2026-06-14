<?php

namespace DBM\Tests\Unit;

use DBM\Rest\PingController;
use DBM\Sync\PayloadVerifier;
use PHPUnit\Framework\TestCase;

class PingControllerTest extends TestCase
{
    public function test_valid_ping_triggers_sync(): void
    {
        $body = json_encode(['domain' => 'd.ua', 'version' => 6]);
        $sig = hash_hmac('sha256', $body, 'ping-secret');
        $synced = false;

        $controller = new PingController(new PayloadVerifier(), 'ping-secret', function () use (&$synced) {
            $synced = true;
        });
        $status = $controller->handle($body, $sig);

        $this->assertSame(202, $status);
        $this->assertTrue($synced);
    }

    public function test_invalid_signature_is_rejected_without_sync(): void
    {
        $synced = false;
        $controller = new PingController(new PayloadVerifier(), 'ping-secret', function () use (&$synced) {
            $synced = true;
        });

        $this->assertSame(401, $controller->handle('{"domain":"d.ua","version":6}', 'wrong'));
        $this->assertFalse($synced);
    }
}
