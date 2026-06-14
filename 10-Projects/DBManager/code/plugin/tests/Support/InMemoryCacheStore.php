<?php

namespace DBM\Tests\Support;

use DBM\Cache\CacheStore;

class InMemoryCacheStore implements CacheStore
{
    private ?array $payload = null;

    public function get(): ?array
    {
        return $this->payload;
    }

    public function version(): int
    {
        return (int) ($this->payload['version'] ?? 0);
    }

    public function put(array $payload): void
    {
        $this->payload = $payload;
    }

    public function clear(): void
    {
        $this->payload = null;
    }
}
