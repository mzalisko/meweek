<?php

namespace Database\Seeders;

use App\Models\ValueType;
use Illuminate\Database\Seeder;

class ValueTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'phone', 'name' => 'Телефон'],
            ['code' => 'messenger', 'name' => 'Месенджер'],
            ['code' => 'price', 'name' => 'Ціна'],
            ['code' => 'address', 'name' => 'Адреса'],
            ['code' => 'social', 'name' => 'Соцмережа'],
            ['code' => 'text', 'name' => 'Текст'],
        ] as $type) {
            ValueType::firstOrCreate(['code' => $type['code']], $type);
        }
    }
}
