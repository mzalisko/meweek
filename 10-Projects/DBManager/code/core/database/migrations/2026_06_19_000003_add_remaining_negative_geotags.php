<?php

use App\Models\GeoTag;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
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

    public function down(): void
    {
        GeoTag::whereIn('code', [
            'KZ', 'PL', 'RO',
            '!UA', '!RU', '!BY', '!KZ', '!PL', '!RO'
        ])->where('is_protected', false)->delete();
    }
};
