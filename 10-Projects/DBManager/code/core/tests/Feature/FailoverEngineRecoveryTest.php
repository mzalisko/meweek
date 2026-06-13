<?php

namespace Tests\Feature;

use App\Services\Failover\FailoverEngine;
use App\Services\Failover\SlotResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class FailoverEngineRecoveryTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    public function test_auto_mode_returns_to_recovered_primary(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $engine = app(FailoverEngine::class);

        $engine->markNumberDown($entries[0]->phoneNumber);
        $engine->markNumberActive($entries[0]->phoneNumber->fresh());

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());
        $this->assertSame('ok', $resolved->state);
        $this->assertSame($entries[0]->phoneNumber->e164, $resolved->number);
    }

    public function test_sticky_mode_stays_on_reserve_after_recovery(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(
            ['active', 'active'],
            ['return_mode' => 'sticky']
        );
        $engine = app(FailoverEngine::class);

        $engine->markNumberDown($entries[0]->phoneNumber);
        $engine->markNumberActive($entries[0]->phoneNumber->fresh());

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());
        $this->assertSame('on_reserve', $resolved->state);
        $this->assertSame($entries[1]->phoneNumber->e164, $resolved->number);
    }

    public function test_recovery_from_exhausted_restores_output(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $engine = app(FailoverEngine::class);

        $engine->markNumberDown($entries[0]->phoneNumber);
        $this->assertFalse(app(SlotResolver::class)->resolve($slot->fresh())->visible);

        $engine->markNumberActive($entries[0]->phoneNumber->fresh());

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());
        $this->assertTrue($resolved->visible);
        $this->assertSame('ok', $resolved->state);
    }

    public function test_pin_then_unpin_returns_to_auto_resolution(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active', 'active']);
        $engine = app(FailoverEngine::class);

        $engine->pin($slot->fresh(), $entries[2]);
        $this->assertSame(
            $entries[2]->phoneNumber->e164,
            app(SlotResolver::class)->resolve($slot->fresh())->number
        );

        $engine->unpin($slot->fresh());
        $resolved = app(SlotResolver::class)->resolve($slot->fresh());
        $this->assertSame('ok', $resolved->state);
        $this->assertSame($entries[0]->phoneNumber->e164, $resolved->number);
    }
}
