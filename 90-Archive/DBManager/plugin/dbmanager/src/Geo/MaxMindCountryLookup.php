<?php

namespace DBM\Geo;

use MaxMind\Db\Reader;

class MaxMindCountryLookup implements CountryLookup
{
    public function __construct(private string $dbPath) {}

    public function country(string $ip): ?string
    {
        if (! is_file($this->dbPath) || $ip === '') {
            return null;
        }
        try {
            $reader = new Reader($this->dbPath);
            $record = $reader->get($ip);
            $reader->close();
        } catch (\Throwable) {
            return null;
        }

        return $record['country']['iso_code'] ?? null;
    }
}
