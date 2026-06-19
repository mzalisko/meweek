<?php

namespace DBM\Tests\Unit;

use DBM\Sync\PayloadVerifier;
use PHPUnit\Framework\TestCase;

class PayloadVerifierTest extends TestCase
{
    public function test_accepts_correct_signature(): void
    {
        $body = '{"site":"domen.ua","version":4}';
        $sig = hash_hmac('sha256', $body, 'secret');

        $this->assertTrue((new PayloadVerifier())->verify($body, $sig, 'secret'));
    }

    public function test_rejects_wrong_signature(): void
    {
        $this->assertFalse((new PayloadVerifier())->verify('{"a":1}', 'deadbeef', 'secret'));
    }

    public function test_rejects_empty_secret(): void
    {
        $body = '{"a":1}';
        $sig = hash_hmac('sha256', $body, '');

        // Порожній секрет — fail-closed навіть за «правильного» підпису порожнім ключем.
        $this->assertFalse((new PayloadVerifier())->verify($body, $sig, ''));
    }
}
