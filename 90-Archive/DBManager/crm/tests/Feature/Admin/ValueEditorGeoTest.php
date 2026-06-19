<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValueEditor;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValueEditorGeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function geo(string $code, string $name): GeoTag
    {
        return GeoTag::firstOrCreate(['code' => $code], ['name' => $name]);
    }

    public function test_edit_loads_existing_geo_tags(): void
    {
        $site = Site::factory()->create();
        $geo  = $this->geo('UA', 'Україна');
        $dv   = DataValue::factory()->forSite($site)->create(['key' => 'k']);
        $dv->geoTags()->attach($geo->id);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->assertSet('geoTagIds', [$geo->id]);
    }

    public function test_save_syncs_geo_tags_on_update(): void
    {
        $site  = Site::factory()->create();
        $ua    = $this->geo('UA', 'Україна');
        $world = $this->geo('WORLD', 'Світ');
        $dv    = DataValue::factory()->forSite($site)->create(['key' => 'k']);
        $dv->geoTags()->attach($ua->id);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('geoTagIds', [$world->id])
            ->call('save');

        $this->assertSame([$world->id], $dv->fresh()->geoTags->pluck('id')->all());
    }

    public function test_save_creates_audit_for_geo_change(): void
    {
        $site = Site::factory()->create();
        $ua   = $this->geo('UA', 'Україна');
        $dv   = DataValue::factory()->forSite($site)->create(['key' => 'k']);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('geoTagIds', [$ua->id])
            ->call('save');

        $this->assertTrue(AuditLog::where('action', 'value.geo_changed')->exists());
    }

    public function test_create_syncs_geo_tags(): void
    {
        $site = Site::factory()->create();
        $ua   = $this->geo('UA', 'Україна');

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('key', 'newkey')
            ->set('value', 'val')
            ->set('geoTagIds', [$ua->id])
            ->call('save');

        $dv = DataValue::where('key', 'newkey')->first();
        $this->assertNotNull($dv);
        $this->assertSame([$ua->id], $dv->geoTags->pluck('id')->all());
    }

    public function test_no_audit_when_geo_unchanged(): void
    {
        $site = Site::factory()->create();
        $ua   = $this->geo('UA', 'Україна');
        $dv   = DataValue::factory()->forSite($site)->create(['key' => 'k']);
        $dv->geoTags()->attach($ua->id);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('value', 'new-val')
            ->call('save');

        $this->assertFalse(AuditLog::where('action', 'value.geo_changed')->exists());
    }
}
