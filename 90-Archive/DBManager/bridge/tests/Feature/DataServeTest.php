<?php

namespace Tests\Feature;

use App\Models\PublishedSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataServeTest extends TestCase
{
    use RefreshDatabase;

    private const SIGN = 'data-signing-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.data.signing_secret' => self::SIGN]);
    }

    private function makeSite(string $rawToken, array $over = []): PublishedSite
    {
        return PublishedSite::factory()->create(array_merge([
            'domain' => 'domen.ua',
            'token_hash' => hash('sha256', $rawToken),
            'version' => 4,
            'payload' => ['site' => 'domen.ua', 'version' => 4, 'values' => [['key' => 'phone_ua_1']]],
        ], $over));
    }

    private function fetchData(string $rawToken, array $headers = [])
    {
        return $this->getJson('/api/v1/data', array_merge([
            'X-Site-Token' => $rawToken,
        ], $headers));
    }

    public function test_unknown_token_is_unauthorized(): void
    {
        $this->makeSite('good-token');

        $this->fetchData('bad-token')->assertStatus(401);
    }

    public function test_missing_token_is_unauthorized(): void
    {
        $this->getJson('/api/v1/data')->assertStatus(401);
    }

    public function test_valid_token_returns_signed_payload_with_etag(): void
    {
        $site = $this->makeSite('good-token');

        $response = $this->fetchData('good-token')->assertOk();

        $response->assertHeader('ETag', '"4"');
        $body = $response->getContent();
        $expectedSig = hash_hmac('sha256', $body, self::SIGN);
        $response->assertHeader('X-Signature', $expectedSig);

        $json = $response->json();
        $this->assertSame('domen.ua', $json['site']);
        $this->assertSame(4, $json['version']);
    }

    public function test_missing_signing_secret_fails_closed(): void
    {
        // Без секрета підпису serve не має віддавати дані з порожнім ключем — лише 500.
        config(['services.data.signing_secret' => null]);
        $this->makeSite('good-token');

        $this->fetchData('good-token')->assertStatus(500);
    }
}
