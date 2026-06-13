<?php

namespace App\Services\Provisioning;

use App\Models\ApiToken;
use App\Models\Site;
use Illuminate\Support\Str;

class SiteProvisioner
{
    /** Згенерувати токен сайта; повертає СИРИЙ токен один раз (у БД лише хеш). */
    public function issueToken(Site $site, ?string $label = null): string
    {
        $raw = Str::random(48);

        ApiToken::create([
            'site_id' => $site->id,
            'token_hash' => hash('sha256', $raw),
            'label' => $label,
        ]);

        return $raw;
    }

    /** Хеш останнього чинного (невідкликаного) токена сайта або null. */
    public function activeTokenHash(Site $site): ?string
    {
        return ApiToken::where('site_id', $site->id)
            ->whereNull('revoked_at')
            ->latest('id')
            ->value('token_hash');
    }
}
