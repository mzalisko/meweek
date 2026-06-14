<?php

namespace DBM\Sync;

use DBM\Cache\CacheStore;
use DBM\Http\DataClient;

class Synchronizer
{
    public function __construct(
        private DataClient $client,
        private CacheStore $cache,
        private PayloadVerifier $verifier,
        private string $url,
        private string $token,
        private string $signingSecret,
    ) {}

    /** Повний тяг (на пінг / ручну перевірку): без If-None-Match. */
    public function sync(): SyncResult
    {
        return $this->pull(null);
    }

    /** Добова звірка: з If-None-Match поточної версії. */
    public function reconcile(): SyncResult
    {
        return $this->pull($this->cache->version() ?: null);
    }

    private function pull(?int $ifNoneMatch): SyncResult
    {
        $current = $this->cache->version();
        $response = $this->client->fetch($this->url, $this->token, $ifNoneMatch);

        if ($response['status'] === 304) {
            return new SyncResult(false, $current, 'not-modified');
        }
        if ($response['status'] !== 200) {
            return new SyncResult(false, $current, 'unreachable'); // кеш лишається
        }
        if (! $this->verifier->verify($response['body'], $response['signature'], $this->signingSecret)) {
            return new SyncResult(false, $current, 'bad-signature'); // кеш лишається
        }

        $payload = json_decode($response['body'], true);
        if (! is_array($payload) || ! isset($payload['version'])) {
            return new SyncResult(false, $current, 'malformed');
        }

        $this->cache->put($payload);

        return new SyncResult(true, (int) $payload['version'], 'updated');
    }
}
