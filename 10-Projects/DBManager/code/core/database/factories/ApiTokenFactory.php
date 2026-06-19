<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApiTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'token_hash' => hash('sha256', Str::random(48)),
            'push_secret' => Str::random(64),
            'label' => null,
            'revoked_at' => null,
        ];
    }
}
