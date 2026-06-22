<?php

namespace Tests\Feature;

use App\Services\Publishing\GeoDatabasePublisher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeoDatabasePublisherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bridge.geodb_url' => 'https://bridge.local/api/internal/geodb',
            'services.bridge.publish_secret' => 'pub-secret',
        ]);
    }

    public function test_publishes_signed_database_bytes_with_sha(): void
    {
        Http::fake(['*' => Http::response(['stored_sha' => '...'], 200)]);
        $bytes = random_bytes(64);
        $tmp = tempnam(sys_get_temp_dir(), 'mmdb');
        file_put_contents($tmp, $bytes);

        $ok = app(GeoDatabasePublisher::class)->publish($tmp);

        $this->assertTrue($ok);
        Http::assertSent(function ($request) use ($bytes) {
            $sig = hash_hmac('sha256', $request->body(), 'pub-secret');

            return $request->url() === 'https://bridge.local/api/internal/geodb'
                && $request->body() === $bytes
                && $request->hasHeader('X-Signature', $sig)
                && $request->hasHeader('X-Geodb-Sha256', hash('sha256', $bytes));
        });
        @unlink($tmp);
    }

    public function test_returns_false_when_file_missing(): void
    {
        Http::fake();
        $this->assertFalse(app(GeoDatabasePublisher::class)->publish('/no/such.mmdb'));
        Http::assertNothingSent();
    }
}
