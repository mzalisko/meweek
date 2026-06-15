<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValueEditor;
use App\Models\DataValue;
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

    public function test_saving_group_value_publishes_all_group_sites(): void
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
            ->set('type', 'price')
            ->set('key', 'p')
            ->set('value', '9')
            ->set('scope', 'group')
            ->call('save');

        Http::assertSent(fn ($r) => json_decode($r->body(), true)['domain'] === 'a.ua');
        Http::assertSent(fn ($r) => json_decode($r->body(), true)['domain'] === 'b.ua');
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
            ->set('type', 'price')
            ->set('key', 'price_site')
            ->set('value', '42')
            ->set('scope', 'site')
            ->call('save');

        Http::assertSent(fn ($r) => json_decode($r->body(), true)['domain'] === 'a.ua');
        Http::assertNotSent(fn ($r) => json_decode($r->body(), true)['domain'] === 'b.ua');
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

        Http::assertSent(fn ($r) => json_decode($r->body(), true)['domain'] === 'del.ua');
    }

    public function test_override_for_site_publishes_that_site(): void
    {
        Http::fake(['*' => Http::response(['stored_version' => 1], 200)]);
        $this->actingAs(User::factory()->create());

        $group = SiteGroup::factory()->create();
        $site  = Site::factory()->for($group, 'group')->create(['domain' => 'over.ua']);
        app(SiteProvisioner::class)->issueToken($site);

        $groupValue = DataValue::factory()->forGroup($group)->create(['key' => 'over_key']);

        Livewire::test(ValueEditor::class)
            ->call('overrideForSite', $groupValue->id, $site->id);

        Http::assertSent(fn ($r) => json_decode($r->body(), true)['domain'] === 'over.ua');
    }
}
