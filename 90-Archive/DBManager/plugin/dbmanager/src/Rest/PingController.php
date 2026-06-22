<?php

namespace DBM\Rest;

use DBM\Cache\CacheStore;
use DBM\Sync\PayloadVerifier;

class PingController
{
    public function __construct(
        private PayloadVerifier $verifier,
        private CacheStore $cache,
        private string $signingSecret,
    ) {}

    /** Returns HTTP status: 200 accepted, 400 malformed, 401 bad signature, 409 stale version. */
    public function handle(string $rawBody, string $signature, string $timestamp): int
    {
        if (! ctype_digit($timestamp)) {
            return 401;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return 401;
        }

        if (! $this->verifier->verify($timestamp.'.'.$rawBody, $signature, $this->signingSecret)) {
            return 401;
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload) || ! isset($payload['version']) || ! is_array($payload['values'] ?? null)) {
            return 400;
        }

        $incomingVersion = (int) $payload['version'];
        $incomingSiteId = (int) ($payload['site_id'] ?? 0);
        $current = $this->cache->get();
        $currentVersion = (int) ($current['version'] ?? 0);
        $currentSiteId = (int) ($current['site_id'] ?? 0);

        // Монотонність версії застосовується ЛИШЕ в межах одного site_id. Якщо site_id
        // змінився (перепідключення плагіна до іншого сайту) або кеш порожній — приймаємо
        // дані незалежно від версії. Безпека збережена: підпис уже перевірено вище, а
        // захист від повтору старих даних діє в межах того самого сайту.
        $sameSite = $incomingSiteId === $currentSiteId;

        if ($sameSite && $incomingVersion < $currentVersion) {
            return 409;
        }

        if (! $sameSite || $incomingVersion > $currentVersion) {
            $this->cache->put($payload);
        }

        return 200;
    }
}
