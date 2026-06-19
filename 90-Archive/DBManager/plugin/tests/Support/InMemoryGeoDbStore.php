<?php

namespace DBM\Tests\Support;

use DBM\Geo\GeoDbStore;

class InMemoryGeoDbStore implements GeoDbStore
{
    private ?string $bytes = null;

    public function sha(): ?string
    {
        return $this->bytes === null ? null : hash('sha256', $this->bytes);
    }

    public function bytes(): ?string
    {
        return $this->bytes;
    }

    public function put(string $bytes): void
    {
        $this->bytes = $bytes;
    }

    public function path(): string
    {
        return '/tmp/fake.mmdb';
    }
}
