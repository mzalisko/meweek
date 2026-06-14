<?php

namespace DBM\Cache;

class WpOptionCacheStore implements CacheStore
{
    private const OPTION = 'dbm_cache';

    public function get(): ?array
    {
        $stored = get_option(self::OPTION);

        return is_array($stored) ? $stored : null;
    }

    public function version(): int
    {
        return (int) ($this->get()['version'] ?? 0);
    }

    public function put(array $payload): void
    {
        // autoload=true: доступний на фронті без зайвого запиту; не чиститься при деактивації.
        update_option(self::OPTION, $payload, true);
    }

    public function clear(): void
    {
        delete_option(self::OPTION);
    }
}
