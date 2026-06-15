<?php

namespace Tests\Feature\Admin;

use App\Admin\SiteGridReader;
use App\Livewire\ValueEditor;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use App\Models\ValueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValueOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroupValue(SiteGroup $group, array $attrs = []): DataValue
    {
        $type = ValueType::firstOrCreate(['code' => 'price'], ['name' => 'price']);

        return DataValue::create(array_merge([
            'key'           => 'price_basic',
            'value_type_id' => $type->id,
            'scope_type'    => 'group',
            'scope_id'      => $group->id,
            'content'       => ['value' => '1200'],
            'status'        => 'active',
        ], $attrs));
    }

    public function test_override_creates_site_scoped_copy(): void
    {
        $this->actingAs(User::factory()->create());

        $group      = SiteGroup::factory()->create();
        $site       = Site::factory()->for($group, 'group')->create();
        $groupValue = $this->makeGroupValue($group);

        Livewire::test(ValueEditor::class)
            ->call('overrideForSite', $groupValue->id, $site->id);

        $siteValue = DataValue::where('key', 'price_basic')
            ->where('scope_type', 'site')
            ->where('scope_id', $site->id)
            ->first();

        $this->assertNotNull($siteValue, 'Site-scoped DataValue must be created');
        $this->assertSame($groupValue->key, $siteValue->key);
        $this->assertSame($groupValue->content, $siteValue->content);
        $this->assertSame($groupValue->value_type_id, $siteValue->value_type_id);
    }

    public function test_override_row_shows_site_scope_in_grid(): void
    {
        $this->actingAs(User::factory()->create());

        $group      = SiteGroup::factory()->create();
        $site       = Site::factory()->for($group, 'group')->create();
        $groupValue = $this->makeGroupValue($group);

        Livewire::test(ValueEditor::class)
            ->call('overrideForSite', $groupValue->id, $site->id);

        $rows = app(SiteGridReader::class)->forSite($site);

        $priceRows = $rows['price'] ?? [];
        $row = collect($priceRows)->firstWhere('key', 'price_basic');

        $this->assertNotNull($row, 'Row for price_basic must be in grid');
        $this->assertSame('site', $row['scope'], 'Override must win: scope should be site');
    }

    public function test_override_creates_audit_log(): void
    {
        $this->actingAs(User::factory()->create());

        $group      = SiteGroup::factory()->create();
        $site       = Site::factory()->for($group, 'group')->create();
        $groupValue = $this->makeGroupValue($group);

        Livewire::test(ValueEditor::class)
            ->call('overrideForSite', $groupValue->id, $site->id);

        $this->assertTrue(
            AuditLog::where('action', 'value.overridden')->exists(),
            'AuditLog entry for value.overridden must be created'
        );
    }

    public function test_override_dispatches_value_saved(): void
    {
        $this->actingAs(User::factory()->create());

        $group      = SiteGroup::factory()->create();
        $site       = Site::factory()->for($group, 'group')->create();
        $groupValue = $this->makeGroupValue($group);

        Livewire::test(ValueEditor::class)
            ->call('overrideForSite', $groupValue->id, $site->id)
            ->assertDispatched('value-saved');
    }

    public function test_override_ignores_non_group_scoped_value(): void
    {
        $this->actingAs(User::factory()->create());

        $site      = Site::factory()->create();
        $type      = ValueType::firstOrCreate(['code' => 'text'], ['name' => 'text']);
        $siteValue = DataValue::create([
            'key'           => 'some_key',
            'value_type_id' => $type->id,
            'scope_type'    => 'site',
            'scope_id'      => $site->id,
            'content'       => ['value' => 'hello'],
            'status'        => 'active',
        ]);

        Livewire::test(ValueEditor::class)
            ->call('overrideForSite', $siteValue->id, $site->id);

        // No second site-scoped DataValue should be created for same key
        $this->assertSame(
            1,
            DataValue::where('key', 'some_key')->where('scope_type', 'site')->count(),
            'Guard: must not create override for a non-group-scoped value'
        );
    }

    public function test_override_ignores_site_not_in_group(): void
    {
        $this->actingAs(User::factory()->create());

        $group      = SiteGroup::factory()->create();
        $otherSite  = Site::factory()->create(); // no group
        $groupValue = $this->makeGroupValue($group);

        Livewire::test(ValueEditor::class)
            ->call('overrideForSite', $groupValue->id, $otherSite->id);

        $this->assertSame(
            0,
            DataValue::where('key', 'price_basic')->where('scope_type', 'site')->count(),
            'Guard: must not create override when site is not in the value\'s group'
        );
    }
}
