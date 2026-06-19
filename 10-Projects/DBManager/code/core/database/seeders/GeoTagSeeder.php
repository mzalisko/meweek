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
            ['code' => '!RU', 'name' => 'Крім Росії', 'is_protected' => false],
            ['code' => '!BY', 'name' => 'Крім Білорусі', 'is_protected' => false],
        ] as $tag) {
            GeoTag::firstOrCreate(['code' => $tag['code']], $tag);
        }
    }
}
