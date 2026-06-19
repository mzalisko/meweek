<?php

namespace DBM\Http;

interface DataClient
{
    /**
     * GET даних bridge.
     * @return array{status:int, body:string, signature:string, etag:string}
     */
    public function fetch(string $url, string $token, ?int $ifNoneMatchVersion): array;
}
