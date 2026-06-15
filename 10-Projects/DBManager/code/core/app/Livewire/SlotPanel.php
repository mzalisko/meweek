<?php

namespace App\Livewire;

use App\Models\DataValue;
use App\Services\Failover\FailoverEngine;
use App\Services\Failover\ResolvedSlot;
use App\Services\Failover\SlotResolver;
use Livewire\Attributes\On;
use Livewire\Component;

class SlotPanel extends Component
{
    public bool $open = false;

    public ?int $dataValueId = null;

    #[On('open-slot')]
    public function open(int $dataValueId): void
    {
        $value = DataValue::with([
            'phoneSlot.entries.phoneNumber',
            'phoneSlot',
            'type',
        ])->find($dataValueId);

        if (! $value || ! $value->phoneSlot) {
            $this->open = false;

            return;
        }

        $this->dataValueId = $dataValueId;
        $this->open = true;
    }

    public function pin(int $entryId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot.entries')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $slot  = $value->phoneSlot;
        $entry = $slot->entries->firstWhere('id', $entryId);

        if (! $entry) {
            // Entry does not belong to this slot — reject silently
            return;
        }

        app(FailoverEngine::class)->pin($slot, $entry, 'user');
    }

    public function unpin(): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        app(FailoverEngine::class)->unpin($value->phoneSlot, 'user');
    }

    public function render()
    {
        $value    = null;
        $slot     = null;
        $entries  = collect();
        $resolved = null;

        if ($this->open && $this->dataValueId) {
            $value = DataValue::with([
                'phoneSlot.entries.phoneNumber',
                'phoneSlot',
                'geoTags',
                'type',
            ])->find($this->dataValueId);

            if ($value && $value->phoneSlot) {
                $slot    = $value->phoneSlot;
                $entries = $slot->entries->sortBy('priority');

                try {
                    $resolved = app(SlotResolver::class)->resolve($slot);
                } catch (\Throwable) {
                    $resolved = null;
                }
            } else {
                $this->open = false;
            }
        }

        return view('livewire.slot-panel', [
            'value'    => $value,
            'slot'     => $slot,
            'entries'  => $entries,
            'resolved' => $resolved,
        ]);
    }
}
