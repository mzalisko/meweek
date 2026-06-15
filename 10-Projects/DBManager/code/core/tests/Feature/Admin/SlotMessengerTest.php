<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotMessengerTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_toggle_messenger_disables_enabled_messenger(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_ua_2', 'scope_type' => 'site', 'scope_id' => $site->id]);

        $msg = DataValue::factory()
            ->ofType('messenger')
            ->forSite($site)
            ->create([
                'content' => ['network' => 'viber', 'linked_slot' => 'phone_ua_2', 'enabled' => true],
            ]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('toggleMessenger', $msg->id);

        $this->assertFalse($msg->fresh()->content['enabled']);
        $this->assertTrue(AuditLog::where('action', 'messenger.toggled')->exists());
    }

    public function test_toggle_messenger_enables_disabled_messenger(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_ua_2', 'scope_type' => 'site', 'scope_id' => $site->id]);

        $msg = DataValue::factory()
            ->ofType('messenger')
            ->forSite($site)
            ->create([
                'content' => ['network' => 'telegram', 'linked_slot' => 'phone_ua_2', 'enabled' => false],
            ]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('toggleMessenger', $msg->id);

        $this->assertTrue($msg->fresh()->content['enabled']);
    }

    public function test_toggle_twice_returns_to_original_state(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_ua_2', 'scope_type' => 'site', 'scope_id' => $site->id]);

        $msg = DataValue::factory()
            ->ofType('messenger')
            ->forSite($site)
            ->create([
                'content' => ['network' => 'viber', 'linked_slot' => 'phone_ua_2', 'enabled' => true],
            ]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('toggleMessenger', $msg->id)
            ->call('toggleMessenger', $msg->id);

        $this->assertTrue($msg->fresh()->content['enabled']);
    }

    public function test_toggle_messenger_writes_audit_log_with_old_and_new(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_ua_2', 'scope_type' => 'site', 'scope_id' => $site->id]);

        $msg = DataValue::factory()
            ->ofType('messenger')
            ->forSite($site)
            ->create([
                'content' => ['network' => 'viber', 'linked_slot' => 'phone_ua_2', 'enabled' => true],
            ]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('toggleMessenger', $msg->id);

        $log = AuditLog::where('action', 'messenger.toggled')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->old['enabled']);
        $this->assertFalse($log->new['enabled']);
        $this->assertSame('DataValue', $log->subject_type);
        $this->assertSame($msg->id, $log->subject_id);
    }

    public function test_toggle_messenger_not_linked_to_slot_is_ignored(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_ua_2', 'scope_type' => 'site', 'scope_id' => $site->id]);

        // Messenger linked to a DIFFERENT slot key
        $other = DataValue::factory()
            ->ofType('messenger')
            ->forSite($site)
            ->create([
                'content' => ['network' => 'viber', 'linked_slot' => 'phone_other', 'enabled' => true],
            ]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('toggleMessenger', $other->id);

        // Should NOT be toggled
        $this->assertTrue($other->fresh()->content['enabled']);
        $this->assertFalse(AuditLog::where('action', 'messenger.toggled')->exists());
    }

    public function test_panel_shows_linked_messengers_in_view(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_ua_2', 'scope_type' => 'site', 'scope_id' => $site->id]);

        DataValue::factory()
            ->ofType('messenger')
            ->forSite($site)
            ->create([
                'content' => ['network' => 'viber', 'linked_slot' => 'phone_ua_2', 'enabled' => true],
            ]);

        // Назва мережі рендериться через ucfirst (як у макеті: Viber/WhatsApp).
        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->assertSee('Viber');
    }
}
