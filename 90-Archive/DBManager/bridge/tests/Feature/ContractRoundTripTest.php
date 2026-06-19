<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContractRoundTripTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.publish.secret' => 'pub',
            'services.data.signing_secret' => 'sign',
        ]);
        Queue::fake();
    }

    public function test_core_shaped_payload_ingested_and_served_intact(): void
    {
        $rawToken = 'site-raw-token';

        // payload рівно у формі, що компілює Core (плани 1.1)
        $payload = [
            'site' => 'domen.ua',
            'version' => 2,
            'generated_at' => '2026-06-13T10:00:00+00:00',
            'values' => [
                ['key' => 'phone_ua_1', 'type' => 'phone', 'geo' => ['UA'], 'state' => 'ok', 'value' => '+380441234567'],
                ['key' => 'viber_ua_2', 'type' => 'messenger', 'geo' => ['UA'], 'network' => 'viber',
                 'state' => 'ok', 'value' => '+380671112233', 'url' => 'viber://chat?number=%2B380671112233'],
                ['key' => 'price_basic', 'type' => 'price', 'geo' => ['WORLD'], 'value' => '1200', 'currency' => 'UAH'],
            ],
        ];

        $ingestBody = json_encode([
            'domain' => 'domen.ua',
            'token_hash' => hash('sha256', $rawToken),
            'push_secret' => 'site-listener-secret-with-enough-length',
            'ping_url' => 'https://domen.ua/ping',
            'version' => 2,
            'payload' => $payload,
        ]);

        // 1. Core → Bridge ingest
        $this->call('POST', '/api/internal/publish', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $ingestBody, 'pub'),
            'HTTP_ACCEPT' => 'application/json',
        ], $ingestBody)->assertOk();

        // 2. Сайт → Bridge serve
        $response = $this->getJson('/api/v1/data', ['X-Site-Token' => $rawToken])->assertOk();

        // 3. Контракт цілий за значеннями (порядок ключів об'єкта не є частиною
        //    контракту — сховище MySQL нормалізує порядок ключів JSON; плагін
        //    читає за ключем, а підпис рахується над тілом, що віддається).
        $served = $response->json();
        $this->assertEquals($payload, $served);
        $this->assertSame('+380671112233', $served['values'][1]['value']);

        // 4. Підпис відповіді валідний
        $body = $response->getContent();
        $response->assertHeader('X-Signature', hash_hmac('sha256', $body, 'sign'));
    }
}
