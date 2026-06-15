<?php

namespace Tests\Feature\Admin;

use App\Admin\SiteGridReader;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use App\Models\SiteGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SiteGridReaderTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    public function test_groups_rows_by_type_with_scope_and_status(): void
    {
        $group = SiteGroup::factory()->create();
        $site = Site::factory()->for($group, 'group')->create();

        // груповий телефон-слот, активний (2 entries: current + 1 reserve)
        [$slot] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update(['key' => 'phone_ua_1', 'scope_type' => 'group', 'scope_id' => $group->id]);
        $slot->dataValue->geoTags()->attach(GeoTag::where('code', 'UA')->first());

        // власне (site-override) значення-ціна
        DataValue::factory()->forSite($site)->ofType('price')->create([
            'key' => 'price_basic', 'content' => ['value' => '1200'],
        ]);

        $rows = app(SiteGridReader::class)->forSite($site);

        $phones = collect($rows['phone'] ?? []);
        $phone = $phones->firstWhere('key', 'phone_ua_1');
        $this->assertNotNull($phone, 'phone_ua_1 row must exist');
        $this->assertSame('group', $phone['scope']);
        $this->assertSame('ok', $phone['state']);
        $this->assertSame(['UA'], $phone['geo']);
        $this->assertSame(1, $phone['reserves']); // 2 entries — 1 current, 1 reserve

        $price = collect($rows['price'] ?? [])->firstWhere('key', 'price_basic');
        $this->assertNotNull($price, 'price_basic row must exist');
        $this->assertSame('site', $price['scope']);
        $this->assertSame('1200', $price['value']);
    }

    public function test_site_override_marked_site_scope(): void
    {
        $group = SiteGroup::factory()->create();
        $site = Site::factory()->for($group, 'group')->create();
        DataValue::factory()->forGroup($group)->create(['key' => 'addr', 'content' => ['value' => 'Г']]);
        DataValue::factory()->forSite($site)->create(['key' => 'addr', 'content' => ['value' => 'С']]);

        $rows = app(SiteGridReader::class)->forSite($site);
        $addr = collect($rows['text'] ?? [])->firstWhere('key', 'addr');

        $this->assertNotNull($addr, 'addr row must exist');
        $this->assertSame('site', $addr['scope']);
        $this->assertSame('С', $addr['value']);
    }
}
