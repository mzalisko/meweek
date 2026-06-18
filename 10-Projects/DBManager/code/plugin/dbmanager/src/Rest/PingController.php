<?php

namespace DBM\Rest;

use DBM\Cache\CacheStore;
use DBM\Sync\PayloadVerifier;

class PingController
{
    public function __construct(
        private PayloadVerifier $verifier,
        private CacheStore $cache,
        private string $signingSecret,
    ) {}

    /** Повертає HTTP-статус: 200 успішно оновлено, 400 пошкоджений payload, 401 невірний підпис. */
    public function handle(string $rawBody, string $signature): int
    {
        if (! $this->verifier->verify($rawBody, $signature, $this->signingSecret)) {
            return 401;
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload) || ! isset($payload['version'])) {
            return 400;
        }

        $this->cache->put($payload);

        return 200;
    }
}
