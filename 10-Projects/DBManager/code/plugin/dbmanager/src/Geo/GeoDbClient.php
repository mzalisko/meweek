<?php

namespace DBM\Geo;

interface GeoDbClient
{
    /** @return array{status:int, body:string, signature:string, etag:string} */
    public function fetch(string $url, string $token, ?string $ifNoneMatchSha): array;
}
