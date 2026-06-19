<?php

namespace DBM\Geo;

use DBM\Sync\PayloadVerifier;

class GeoDbSynchronizer
{
    public function __construct(
        private GeoDbClient $client,
        private GeoDbStore $store,
        private PayloadVerifier $verifier,
        private string $url,
        private string $token,
        private string $signingSecret,
    ) {}

    /** true — базу оновлено. Битий підпис/304/помилка — лишаємо наявну. */
    public function sync(): bool
    {
        $response = $this->client->fetch($this->url, $this->token, $this->store->sha());

        if ($response['status'] !== 200) {
            return false;
        }
        if (! $this->verifier->verify($response['body'], $response['signature'], $this->signingSecret)) {
            return false;
        }

        $this->store->put($response['body']);

        return true;
    }
}
