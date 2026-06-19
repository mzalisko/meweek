<?php

namespace Tests\Feature\Admin;

use App\Admin\SiteGridReader;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Support\PhoneFormatter;
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

        [$slot] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update(['key' => 'phone_ua_1', 'scope_type' => 'site', 'scope_id' => $site->id]);
        $slot->dataValue->geoTags()->attach(GeoTag::where('code', 'UA')->first());

        DataValue::factory()->forSite($site)->ofType('price')->create([
            'key' => 'price_basic', 'content' => ['value' => '1200'],
        ]);

        $rows = app(SiteGridReader::class)->forSite($site);

        $phones = collect($rows['phone'] ?? []);
        $phone = $phones->firstWhere('key', 'phone_ua_1');
        $this->assertNotNull($phone, 'phone_ua_1 row must exist');
        $this->assertSame('site', $phone['scope']);
        $this->assertSame('current_site', $phone['source']);
        $this->assertSame('ok', $phone['state']);
        $this->assertSame(['UA'], $phone['geo']);
        $this->assertSame(1, $phone['reserves']);
        $this->assertSame($slot->fresh()->current_number_entry_id, $phone['entry_id']);
        $this->assertCount(2, $phone['numbers']);
        $this->assertSame(0, $phone['numbers'][0]['priority']);
        $this->assertSame(1, $phone['numbers'][1]['priority']);

        $price = collect($rows['price'] ?? [])->firstWhere('key', 'price_basic');
        $this->assertNotNull($price, 'price_basic row must exist');
        $this->assertSame('site', $price['scope']);
        $this->assertSame('current_site', $price['source']);
        $this->assertSame('1200', $price['value']);
    }

    public function test_hidden_phone_slot_keeps_last_active_value_in_grid(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update([
            'key' => 'phone_hidden',
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'status' => 'hidden',
        ]);

        $rows = app(SiteGridReader::class)->forSite($site);
        $phone = collect($rows['phone'] ?? [])->firstWhere('key', 'phone_hidden');

        $this->assertNotNull($phone);
        $this->assertSame('hidden', $phone['state']);
        $this->assertSame($entries[0]->phoneNumber->e164, $phone['value']);
        $this->assertSame($slot->fresh()->current_number_entry_id, $phone['entry_id']);
        $this->assertCount(2, $phone['numbers']);
        $this->assertSame('active', $phone['numbers'][0]['status']);
        $this->assertTrue($phone['numbers'][0]['is_current']);
    }

    public function test_child_site_does_not_inherit_parent_rows_in_grid(): void
    {
        $group = SiteGroup::factory()->create();
        $parent = Site::factory()->for($group, 'group')->create(['domain' => 'main.test']);
        $child = Site::factory()->for($group, 'group')->create([
            'domain' => 'child.test',
            'parent_site_id' => $parent->id,
        ]);

        DataValue::factory()->forSite($parent)->create([
            'key' => 'addr_main',
            'content' => ['value' => 'Parent value'],
        ]);
        DataValue::factory()->forSite($child)->create([
            'key' => 'addr_child',
            'content' => ['value' => 'Child value'],
        ]);

        $rows = app(SiteGridReader::class)->forSite($child);
        $main = collect($rows['text'] ?? [])->firstWhere('key', 'addr_main');
        $childRow = collect($rows['text'] ?? [])->firstWhere('key', 'addr_child');

        $this->assertNull($main);
        $this->assertNotNull($childRow);
        $this->assertSame('site', $childRow['scope']);
        $this->assertSame('current_site', $childRow['source']);
        $this->assertSame('цього сайту', $childRow['source_label']);
        $this->assertSame('Child value', $childRow['value']);
    }

    public function test_phone_row_contains_display_values_from_format_pattern(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update([
            'key' => 'phone_fmt',
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'content' => ['phone_format' => '+### ## ### ## ##'],
        ]);

        $rows = app(SiteGridReader::class)->forSite($site);
        $phone = collect($rows['phone'] ?? [])->firstWhere('key', 'phone_fmt');

        $this->assertSame($entries[0]->phoneNumber->e164, $phone['value']);
        $expected = PhoneFormatter::format($entries[0]->phoneNumber->e164, '+### ## ### ## ##');
        $this->assertSame($expected, $phone['display_value']);
        $this->assertSame($expected, $phone['numbers'][0]['display_value']);
    }
}
