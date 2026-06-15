<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotNumberAddTest extends TestCase
{
    use RefreshDatabase, BuildsSlots;

    public function test_add_number_creates_entry_at_next_priority(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        $site = \App\Models\Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']); // 1 номер, priority 0
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(\App\Livewire\SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->set('newNumber', '+380441112233')
            ->call('addNumber');

        $slot->refresh();
        $this->assertSame(2, $slot->entries()->count());
        $entry = $slot->entries()->orderByDesc('priority')->first();
        $this->assertSame(1, $entry->priority);
        $this->assertSame('+380441112233', $entry->phoneNumber->e164);
        $this->assertTrue(AuditLog::where('action', 'number.added')->exists());
    }

    public function test_add_number_validates_e164(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());
        [$slot] = $this->slotWithNumbers(['active']);

        Livewire::test(\App\Livewire\SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->set('newNumber', 'не номер')
            ->call('addNumber')
            ->assertHasErrors('newNumber');
    }
}
