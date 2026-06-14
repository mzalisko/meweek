<?php

namespace DBM\Tests\Support;

use DBM\Http\DataClient;

class FakeDataClient implements DataClient
{
    /** @var array<int, array> */
    public array $calls = [];

    public function __construct(private array $response) {}

    public function fetch(string $url, string $token, ?int $ifNoneMatchVersion): array
    {
        $this->calls[] = compact('url', 'token', 'ifNoneMatchVersion');

        return $this->response;
    }
}
