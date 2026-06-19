<?php

namespace DBM\Cache;

interface CacheStore
{
    public function get(): ?array;       // розпакований payload або null

    public function version(): int;      // версія в кеші (0 якщо порожній)

    public function put(array $payload): void;

    public function clear(): void;       // лише за явним запитом
}
