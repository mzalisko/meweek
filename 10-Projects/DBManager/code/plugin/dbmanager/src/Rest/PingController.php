<?php

namespace DBM\Rest;

use DBM\Sync\PayloadVerifier;

class PingController
{
    /** @param callable $onValid викликається при валідному пінгу (запускає синхронізацію) */
    public function __construct(
        private PayloadVerifier $verifier,
        private string $pingSecret,
        private $onValid,
    ) {}

    /** Повертає HTTP-статус: 202 прийнято, 401 битий підпис. */
    public function handle(string $rawBody, string $signature): int
    {
        if (! $this->verifier->verify($rawBody, $signature, $this->pingSecret)) {
            return 401;
        }

        ($this->onValid)();

        return 202;
    }
}
