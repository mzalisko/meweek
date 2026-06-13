<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PublishedSiteFactory extends Factory
{
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'domain' => $domain,
            'token_hash' => hash('sha256', Str::random(48)),
            'ping_url' => 'https://'.$domain.'/wp-json/dbm/v1/ping',
            'version' => 1,
            'payload' => ['site' => $domain, 'version' => 1, 'values' => []],
        ];
    }
}
