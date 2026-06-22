<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\Publication;
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

    public function test_save_publishes_affected_sites_locally(): void
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
            ->call('save')
            ->assertSet('open', false)
            ->assertDispatched('slot-updated');

        // Збереження слота публікує сайт ЛОКАЛЬНО (Publication), без пушу в bridge — синхронізація ручна.
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'internal/publish'));
        $this->assertTrue(Publication::where('site_id', $site->id)->exists());
    }

    public function test_save_phone_format_on_slot(): void
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
            ->set('phoneFormat', '+### (##) ###-##-##')
            ->call('save')
            ->assertSet('open', false);

        $this->assertSame(
            '+### (##) ###-##-##',
            $slot->dataValue->fresh()->content['phone_format'] ?? null
        );
    }
}
