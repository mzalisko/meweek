<?php

namespace Tests\Feature\Admin;

use App\Livewire\BulkOperations;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_bulk_geo_replace_updates_values_in_selected_group(): void
    {
        $group = SiteGroup::factory()->create(['name' => 'Geo group']);
        $siteA = Site::factory()->for($group, 'group')->create(['domain' => 'a.test']);
        $siteB = Site::factory()->for($group, 'group')->create(['domain' => 'b.test']);
        $outside = Site::factory()->create(['domain' => 'outside.test']);
        $ua = GeoTag::firstOrCreate(['code' => 'UA'], ['name' => 'Україна']);

        $valueA = DataValue::factory()->forSite($siteA)->create(['key' => 'address']);
        $valueB = DataValue::factory()->forSite($siteB)->create(['key' => 'address']);
        $outsideValue = DataValue::factory()->forSite($outside)->create(['key' => 'address']);

        Livewire::test(BulkOperations::class)
            ->set('scope', 'group')
            ->set('groupId', $group->id)
            ->set('operation', 'set_geo')
            ->set('geoCodes', ['UA'])
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertSet('report.changed', 2)
            ->assertHasNoErrors();

        $this->assertSame(['UA'], $valueA->fresh('geoTags')->geoTags->pluck('code')->all());
        $this->assertSame(['UA'], $valueB->fresh('geoTags')->geoTags->pluck('code')->all());
        $this->assertSame([], $outsideValue->fresh('geoTags')->geoTags->pluck('code')->all());
        $this->assertDatabaseHas('geo_tags', ['id' => $ua->id, 'code' => 'UA']);
    }

    public function test_clicking_site_switches_to_selected_scope_and_limits_targets(): void
    {
        $siteA = Site::factory()->create(['domain' => 'a.test']);
        $siteB = Site::factory()->create(['domain' => 'b.test']);
        GeoTag::firstOrCreate(['code' => 'UA'], ['name' => 'Україна']);

        $valueA = DataValue::factory()->forSite($siteA)->create(['key' => 'address']);
        $valueB = DataValue::factory()->forSite($siteB)->create(['key' => 'address']);

        Livewire::test(BulkOperations::class)
            ->assertSet('scope', 'all')
            ->call('toggleSite', $siteA->id)
            ->assertSet('scope', 'selected')
            ->assertSet('selectedSiteIds', [$siteA->id])
            ->set('operation', 'set_geo')
            ->set('geoCodes', ['UA'])
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertSet('report.changed', 1)
            ->assertHasNoErrors();

        $this->assertSame(['UA'], $valueA->fresh('geoTags')->geoTags->pluck('code')->all());
        $this->assertSame([], $valueB->fresh('geoTags')->geoTags->pluck('code')->all());
    }

    public function test_select_filtered_sites_switches_to_selected_scope(): void
    {
        $siteA = Site::factory()->create(['domain' => 'a.test']);
        Site::factory()->create(['domain' => 'b.test']);

        Livewire::test(BulkOperations::class)
            ->set('siteSearch', 'a.test')
            ->call('selectFilteredSites')
            ->assertSet('scope', 'selected')
            ->assertSet('selectedSiteIds', [$siteA->id]);
    }

    public function test_bulk_phone_replace_changes_only_matching_numbers(): void
    {
        $siteA = Site::factory()->create(['domain' => 'a.test']);
        $siteB = Site::factory()->create(['domain' => 'b.test']);

        $entryA = $this->phoneEntry($siteA, 'sales_phone', '+380111111111');
        $entryB = $this->phoneEntry($siteB, 'sales_phone', '+380222222222');

        Livewire::test(BulkOperations::class)
            ->set('scope', 'all')
            ->set('targetType', 'phone')
            ->set('phoneFilter', '+380111')
            ->set('operation', 'replace_phone')
            ->set('phoneReplacement', '+380999999999')
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertSet('report.changed', 1)
            ->assertHasNoErrors();

        $this->assertSame('+380999999999', $entryA->fresh('phoneNumber')->phoneNumber->e164);
        $this->assertSame('+380222222222', $entryB->fresh('phoneNumber')->phoneNumber->e164);
    }

    public function test_bulk_phone_replace_requires_phone_filter(): void
    {
        $site = Site::factory()->create(['domain' => 'a.test']);
        $entry = $this->phoneEntry($site, 'sales_phone', '+380111111111');

        Livewire::test(BulkOperations::class)
            ->set('scope', 'all')
            ->set('targetType', 'phone')
            ->set('operation', 'replace_phone')
            ->set('phoneReplacement', '+380999999999')
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertHasErrors('phoneFilter');

        $this->assertSame('+380111111111', $entry->fresh('phoneNumber')->phoneNumber->e164);
    }

    public function test_bulk_geo_replace_updates_nested_price_geo(): void
    {
        $site = Site::factory()->create(['domain' => 'price.test']);
        GeoTag::firstOrCreate(['code' => 'PL'], ['name' => 'Польща']);

        $value = DataValue::factory()
            ->forSite($site)
            ->ofType('price')
            ->create([
                'key' => 'tuition_price',
                'content' => [
                    'prices' => [
                        ['label' => 'standard', 'value' => '100 EUR', 'geo' => ['UA']],
                        ['label' => 'vip', 'value' => '150 EUR', 'geo' => ['WORLD']],
                    ],
                ],
            ]);

        Livewire::test(BulkOperations::class)
            ->set('scope', 'all')
            ->set('targetType', 'price')
            ->set('operation', 'set_geo')
            ->set('geoCodes', ['PL'])
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertSet('report.changed', 1)
            ->assertHasNoErrors();

        $this->assertSame(
            [['PL'], ['PL']],
            collect($value->fresh()->content['prices'])->pluck('geo')->all()
        );
    }

    public function test_bulk_phone_format_updates_format(): void
    {
        $site = Site::factory()->create(['domain' => 'format.test']);
        $value = DataValue::factory()
            ->forSite($site)
            ->ofType('phone')
            ->create(['key' => 'sales_phone', 'content' => []]);

        Livewire::test(BulkOperations::class)
            ->set('scope', 'all')
            ->set('targetType', 'phone')
            ->set('operation', 'set_phone_format')
            ->set('phoneFormat', '+### (##) ###-##-##')
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertSet('report.changed', 1)
            ->assertHasNoErrors();

        $this->assertSame('+### (##) ###-##-##', $value->fresh()->content['phone_format']);
    }

    public function test_bulk_rollback_batch_reverts_changes(): void
    {
        $site = Site::factory()->create(['domain' => 'rollback.test']);
        $value = DataValue::factory()
            ->forSite($site)
            ->ofType('phone')
            ->create(['key' => 'sales_phone', 'content' => []]);

        $lw = Livewire::test(BulkOperations::class)
            ->set('scope', 'all')
            ->set('targetType', 'phone')
            ->set('operation', 'set_phone_format')
            ->set('phoneFormat', '+### (##) ###-##-##')
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertHasNoErrors();

        $this->assertSame('+### (##) ###-##-##', $value->fresh()->content['phone_format']);

        $batchId = $lw->get('report.batch');
        $this->assertNotNull($batchId);

        $lw->call('rollbackBatch', $batchId)
            ->assertHasNoErrors();

        $content = $value->fresh()->content;
        $this->assertTrue(!isset($content['phone_format']) || $content['phone_format'] === '');
    }

    private function phoneEntry(Site $site, string $key, string $e164): NumberEntry
    {
        $value = DataValue::factory()
            ->forSite($site)
            ->ofType('phone')
            ->create(['key' => $key, 'content' => []]);

        $slot = PhoneSlot::factory()->for($value, 'dataValue')->create();
        $number = PhoneNumber::factory()->create(['e164' => $e164, 'status' => 'active']);

        return NumberEntry::factory()
            ->for($slot, 'slot')
            ->for($number, 'phoneNumber')
            ->create(['priority' => 0]);
    }

    public function test_bulk_phone_reserve_changes_only_reserve_numbers(): void
    {
        $site = Site::factory()->create(['domain' => 'reserve.test']);
        $value = DataValue::factory()
            ->forSite($site)
            ->ofType('phone')
            ->create(['key' => 'sales_phone', 'content' => []]);

        $slot = PhoneSlot::factory()->for($value, 'dataValue')->create();
        $primaryNum = PhoneNumber::factory()->create(['e164' => '+380111111111', 'status' => 'active']);
        $reserveNum = PhoneNumber::factory()->create(['e164' => '+380222222222', 'status' => 'active']);

        $primaryEntry = NumberEntry::factory()
            ->for($slot, 'slot')
            ->for($primaryNum, 'phoneNumber')
            ->create(['priority' => 0]);

        $reserveEntry = NumberEntry::factory()
            ->for($slot, 'slot')
            ->for($reserveNum, 'phoneNumber')
            ->create(['priority' => 1]);

        Livewire::test(BulkOperations::class)
            ->set('scope', 'all')
            ->set('targetType', 'phone_reserve')
            ->set('phoneFilter', '+380222')
            ->set('operation', 'replace_phone')
            ->set('phoneReplacement', '+380999999999')
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertHasNoErrors()
            ->assertSet('report.changed', 1);

        $this->assertSame('+380111111111', $primaryEntry->fresh('phoneNumber')->phoneNumber->e164);
        $this->assertSame('+380999999999', $reserveEntry->fresh('phoneNumber')->phoneNumber->e164);
    }

    public function test_bulk_phone_rollback_reverts_assignment(): void
    {
        $site = Site::factory()->create(['domain' => 'rollback.test']);
        $entry = $this->phoneEntry($site, 'sales_phone', '+380111111111');

        $lw = Livewire::test(BulkOperations::class)
            ->set('scope', 'all')
            ->set('targetType', 'phone')
            ->set('phoneFilter', '+380111')
            ->set('operation', 'replace_phone')
            ->set('phoneReplacement', '+380999999999')
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertHasNoErrors()
            ->assertSet('report.changed', 1);

        $this->assertSame('+380999999999', $entry->fresh('phoneNumber')->phoneNumber->e164);

        $batchId = $lw->get('report.batch');
        $this->assertNotNull($batchId);

        $lw->call('rollbackBatch', $batchId)
            ->assertHasNoErrors();

        $this->assertSame('+380111111111', $entry->fresh('phoneNumber')->phoneNumber->e164);
    }
}
