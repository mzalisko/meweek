<?php

use App\Models\GeoTag;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['code' => '!RU', 'name' => 'Крім Росії', 'is_protected' => false],
            ['code' => '!BY', 'name' => 'Крім Білорусі', 'is_protected' => false],
        ] as $tag) {
            GeoTag::firstOrCreate(['code' => $tag['code']], $tag);
        }
    }

    public function down(): void
    {
        GeoTag::whereIn('code', ['!RU', '!BY'])->delete();
    }
};
