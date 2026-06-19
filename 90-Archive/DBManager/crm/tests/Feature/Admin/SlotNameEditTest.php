<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotNameEditTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_renaming_slot_changes_its_key_and_audits(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_old', 'scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->set('slotKey', 'phone_new')
            ->call('renameSlot');

        $this->assertSame('phone_new', $slot->dataValue->fresh()->key);
        $this->assertTrue(AuditLog::where('action', 'slot.renamed')->exists());
    }

    public function test_renaming_slot_relinks_attached_messengers(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_ro_1', 'scope_type' => 'site', 'scope_id' => $site->id]);

        $messenger = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'TG', 'network' => 'telegram', 'linked_slot' => ['phone_ro_1'], 'enabled' => true],
        ]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->set('slotKey', 'phone_ro_2')
            ->call('renameSlot');

        $this->assertSame('phone_ro_2', $slot->dataValue->fresh()->key);
        $this->assertSame(['phone_ro_2'], $messenger->fresh()->content['linked_slot']);
    }

    public function test_renaming_slot_rejects_invalid_key(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_ok', 'scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->set('slotKey', 'Bad Key!')
            ->call('renameSlot')
            ->assertHasErrors('slotKey');

        $this->assertSame('phone_ok', $slot->dataValue->fresh()->key);
    }

    public function test_renaming_slot_rejects_duplicate_key_in_same_scope(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_a', 'scope_type' => 'site', 'scope_id' => $site->id]);

        DataValue::factory()->ofType('phone')->forSite($site)->create(['key' => 'phone_b']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->set('slotKey', 'phone_b')
            ->call('renameSlot')
            ->assertHasErrors('slotKey');

        $this->assertSame('phone_a', $slot->dataValue->fresh()->key);
    }
}
