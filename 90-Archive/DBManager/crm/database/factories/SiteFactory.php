<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_group_id' => null,
            'parent_site_id' => null,
            'name' => fake()->company(),
            'domain' => fake()->unique()->domainName(),
            'country_hint' => null,
        ];
    }
}
