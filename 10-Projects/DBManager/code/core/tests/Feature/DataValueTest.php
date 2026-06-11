<?php

namespace Tests\Feature;

use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_dictionaries_exist(): void
    {
        $this->assertSame(4, GeoTag::count());
        $this->assertTrue(GeoTag::where('code', 'WORLD')->where('is_protected', true)->exists());
    }

    public function test_value_with_geo_tags_for_site_scope(): void
    {
        $site = Site::factory()->create();
        $value = DataValue::factory()->forSite($site)->create(['key' => 'address_main']);
        $value->geoTags()->attach(GeoTag::where('code', 'UA')->first());

        $this->assertSame('site', $value->scope_type);
        $this->assertSame($site->id, $value->scope_id);
        $this->assertSame(['UA'], $value->geoTags->pluck('code')->all());
        $this->assertSame('text', $value->type->code);
    }
}
