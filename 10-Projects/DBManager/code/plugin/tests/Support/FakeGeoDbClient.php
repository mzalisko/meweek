<?php

namespace DBM\Tests\Support;

use DBM\Geo\GeoDbClient;

class FakeGeoDbClient implements GeoDbClient
{
    public array $calls = [];

    public function __construct(private array $response) {}

    public function fetch(string $url, string $token, ?string $ifNoneMatchSha): array
    {
        $this->calls[] = compact('url', 'token', 'ifNoneMatchSha');

        return $this->response;
    }
}
