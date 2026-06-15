<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\Site;
use App\Models\User;
use App\Services\Provisioning\SiteProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotPanelSaveTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_save_publishes_affected_sites_to_bridge(): void
    {
        config([
            'services.bridge.ingest_url'     => 'https://bridge.local/api/internal/publish',
            'services.bridge.publish_secret' => 'pub',
        ]);

        Http::fake(['*' => Http::response(['stored_version' => 1], 200)]);

        $site = Site::factory()->create(['domain' => 'domen.ua']);
        app(SiteProvisioner::class)->issueToken($site);

        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('save');

        Http::assertSent(
            fn ($r) => str_contains($r->url(), 'internal/publish')
                && json_decode($r->body(), true)['domain'] === 'domen.ua'
        );
    }
}
