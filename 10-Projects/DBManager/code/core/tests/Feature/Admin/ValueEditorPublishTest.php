<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValueEditor;
use App\Models\DataValue;
use App\Models\Publication;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use App\Services\Provisioning\SiteProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ValueEditorPublishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bridge.ingest_url'     => 'https://bridge.local/api/internal/publish',
            'services.bridge.publish_secret' => 'pub',
        ]);
    }

    public function test_saving_site_value_publishes_only_that_site(): void
    {
        Http::fake(['*' => Http::response(['stored_version' => 1], 200)]);
        $this->actingAs(User::factory()->create());

        $group = SiteGroup::factory()->create();
        $a     = Site::factory()->for($group, 'group')->create(['domain' => 'a.ua']);
        $b     = Site::factory()->for($group, 'group')->create(['domain' => 'b.ua']);

        app(SiteProvisioner::class)->issueToken($a);
        app(SiteProvisioner::class)->issueToken($b);

        Livewire::test(ValueEditor::class)
            ->call('createFor', $a->id)
            ->set('type', 'messenger')
            ->set('key', 'tg_site')
            ->set('value', 'https://t.me/site')
            ->set('network', 'telegram')
            ->set('scope', 'site')
            ->call('save');

        // Збереження публікує ЛОКАЛЬНО лише уражений сайт; пуш у bridge — ручний (syncCurrentSite).
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'internal/publish'));
        $this->assertTrue(Publication::where('site_id', $a->id)->exists());
        $this->assertFalse(Publication::where('site_id', $b->id)->exists());
    }

    public function test_deleting_value_publishes_affected_sites(): void
    {
        Http::fake(['*' => Http::response(['stored_version' => 1], 200)]);
        $this->actingAs(User::factory()->create());

        $site = Site::factory()->create(['domain' => 'del.ua']);
        app(SiteProvisioner::class)->issueToken($site);

        $dv = DataValue::factory()->forSite($site)->create(['key' => 'to_delete']);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->call('delete');

        // Видалення публікує уражений сайт ЛОКАЛЬНО (Publication), без пушу в bridge.
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'internal/publish'));
        $this->assertTrue(Publication::where('site_id', $site->id)->exists());
    }


}
