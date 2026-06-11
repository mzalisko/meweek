<?php

namespace Tests\Feature;

use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_has_ordered_entries_with_numbers(): void
    {
        $slot = PhoneSlot::factory()->create();
        $second = NumberEntry::factory()->for($slot, 'slot')->create(['priority' => 1]);
        $first = NumberEntry::factory()->for($slot, 'slot')->create(['priority' => 0]);

        $ordered = $slot->fresh()->entries;
        $this->assertSame([$first->id, $second->id], $ordered->pluck('id')->all());
        $this->assertInstanceOf(PhoneNumber::class, $ordered->first()->phoneNumber);
        $this->assertSame('phone', $slot->dataValue->type->code);
    }

    public function test_shared_number_used_by_two_slots(): void
    {
        $number = PhoneNumber::factory()->create();
        $a = NumberEntry::factory()->create(['phone_number_id' => $number->id]);
        $b = NumberEntry::factory()->create(['phone_number_id' => $number->id]);

        $this->assertNotSame($a->phone_slot_id, $b->phone_slot_id);
        $this->assertSame(2, $number->entries()->count());
    }
}
