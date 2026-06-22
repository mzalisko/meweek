<?php

namespace Tests\Feature;

use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Services\Failover\SlotResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeSlot(array $statuses, array $slotAttrs = []): PhoneSlot
    {
        $slot = PhoneSlot::factory()->create($slotAttrs);
        foreach ($statuses as $priority => $status) {
            NumberEntry::factory()->for($slot, 'slot')->create([
                'priority' => $priority,
                'phone_number_id' => PhoneNumber::factory()->create(['status' => $status])->id,
            ]);
        }

        return $slot->fresh();
    }

    public function test_current_active_entry_is_shown_as_ok(): void
    {
        $slot = $this->makeSlot(['active', 'active']);
        $slot->update(['current_number_entry_id' => $slot->entries[0]->id]);

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());

        $this->assertSame('ok', $resolved->state);
        $this->assertSame($slot->entries[0]->phoneNumber->e164, $resolved->number);
        $this->assertTrue($resolved->visible);
    }

    public function test_current_reserve_entry_is_on_reserve(): void
    {
        $slot = $this->makeSlot(['down', 'active']);
        $slot->update(['current_number_entry_id' => $slot->entries[1]->id]);

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());

        $this->assertSame('on_reserve', $resolved->state);
        $this->assertTrue($resolved->visible);
    }

    public function test_pinned_entry_wins_even_if_down(): void
    {
        $slot = $this->makeSlot(['down', 'active']);
        $slot->update([
            'pinned_number_entry_id' => $slot->entries[0]->id,
            'current_number_entry_id' => $slot->entries[1]->id,
        ]);

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());

        $this->assertSame('pinned', $resolved->state);
        $this->assertSame($slot->entries[0]->phoneNumber->e164, $resolved->number);
        $this->assertTrue($resolved->visible);
    }

    public function test_exhausted_hide_policy_hides_output(): void
    {
        $slot = $this->makeSlot(['down', 'down'], ['exhaustion_policy' => 'hide']);
        $slot->update(['current_number_entry_id' => null]);

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());

        $this->assertSame('exhausted', $resolved->state);
        $this->assertNull($resolved->number);
        $this->assertFalse($resolved->visible);
    }

    public function test_exhausted_show_last_policy_keeps_last_number(): void
    {
        $slot = $this->makeSlot(['down'], [
            'exhaustion_policy' => 'show_last',
            'last_active_e164' => '+380999999999',
        ]);
        $slot->update(['current_number_entry_id' => null]);

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());

        $this->assertSame('+380999999999', $resolved->number);
        $this->assertTrue($resolved->visible);
    }

    public function test_exhausted_emergency_policy_uses_emergency_number(): void
    {
        $slot = $this->makeSlot(['down'], [
            'exhaustion_policy' => 'emergency',
            'emergency_number' => '+380800000000',
        ]);
        $slot->update(['current_number_entry_id' => null]);

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());

        $this->assertSame('+380800000000', $resolved->number);
        $this->assertTrue($resolved->visible);
    }
}
