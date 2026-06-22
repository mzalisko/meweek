<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\ValueType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'value_type_id' => fn () => ValueType::firstOrCreate(
                ['code' => 'text'], ['name' => 'Текст']
            )->id,
            'key' => fake()->unique()->slug(2),
            'scope_type' => 'site',
            'scope_id' => Site::factory(),
            'content' => ['value' => fake()->words(2, true)],
            'status' => 'active',
        ];
    }

    public function forSite(Site $site): static
    {
        return $this->state(['scope_type' => 'site', 'scope_id' => $site->id]);
    }

    public function forGroup(SiteGroup $group): static
    {
        return $this->state(['scope_type' => 'group', 'scope_id' => $group->id]);
    }

    public function ofType(string $code): static
    {
        return $this->state([
            'value_type_id' => fn () => ValueType::firstOrCreate(
                ['code' => $code], ['name' => $code]
            )->id,
        ]);
    }
}
