<?php

namespace DBM\Sync;

class PayloadVerifier
{
    /** Перевірка підпису над СИРИМ тілом. Порожній секрет — завжди false (fail-closed). */
    public function verify(string $rawBody, string $signature, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }
}
