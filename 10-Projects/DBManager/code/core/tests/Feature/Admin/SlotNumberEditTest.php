<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotNumberEditTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_edit_number_updates_e164_and_audits(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);
        $entry = $entries[0];

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('startEditNumber', $entry->id)
            ->set('editE164', '+380999888777')
            ->call('saveNumber')
            ->assertSet('editingEntryId', null);

        $this->assertSame('+380999888777', $entry->phoneNumber->fresh()->e164);
        $this->assertTrue(AuditLog::where('action', 'number.edited')->exists());
    }

    public function test_edit_number_validates_e164(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('startEditNumber', $entries[0]->id)
            ->set('editE164', 'не номер')
            ->call('saveNumber')
            ->assertHasErrors('editE164');
    }

    public function test_start_edit_loads_current_e164(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('startEditNumber', $entries[0]->id)
            ->assertSet('editingEntryId', $entries[0]->id)
            ->assertSet('editE164', $entries[0]->phoneNumber->e164);
    }
}
