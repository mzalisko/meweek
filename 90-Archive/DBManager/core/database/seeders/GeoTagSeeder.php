<?php

namespace Database\Seeders;

use App\Models\GeoTag;
use Illuminate\Database\Seeder;

class GeoTagSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'WORLD', 'name' => 'Світ', 'is_protected' => true],
            ['code' => 'UA', 'name' => 'Україна', 'is_protected' => false],
            ['code' => 'RU', 'name' => 'Росія', 'is_protected' => false],
            ['code' => 'BY', 'name' => 'Білорусь', 'is_protected' => false],
            ['code' => 'KZ', 'name' => 'Казахстан', 'is_protected' => false],
            ['code' => 'PL', 'name' => 'Польща', 'is_protected' => false],
            ['code' => 'RO', 'name' => 'Румунія', 'is_protected' => false],
            ['code' => '!UA', 'name' => 'Крім України', 'is_protected' => false],
            ['code' => '!RU', 'name' => 'Крім Росії', 'is_protected' => false],
            ['code' => '!BY', 'name' => 'Крім Білорусі', 'is_protected' => false],
            ['code' => '!KZ', 'name' => 'Крім Казахстану', 'is_protected' => false],
            ['code' => '!PL', 'name' => 'Крім Польщі', 'is_protected' => false],
            ['code' => '!RO', 'name' => 'Крім Румунії', 'is_protected' => false],
        ] as $tag) {
            GeoTag::firstOrCreate(['code' => $tag['code']], $tag);
        }
    }
}
