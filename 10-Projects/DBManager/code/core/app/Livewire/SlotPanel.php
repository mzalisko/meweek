<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\ValueType;
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

    public function setReturnMode(string $mode): void
    {
        if (! in_array($mode, ['auto', 'sticky'], true)) {
            return;
        }

        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $slot = $value->phoneSlot;
        $slot->update(['return_mode' => $mode]);
        app(FailoverEngine::class)->recompute($slot->fresh(), 'user');
    }

    public function setExhaustionPolicy(string $policy): void
    {
        if (! in_array($policy, ['hide', 'last', 'emergency'], true)) {
            return;
        }

        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $value->phoneSlot->update(['exhaustion_policy' => $policy]);
    }

    public function toggleMessenger(int $dataValueId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::find($this->dataValueId);

        if (! $value) {
            return;
        }

        // Load only messengers that are genuinely linked to this slot key
        $linked = $this->loadLinkedMessengers($value);
        $messenger = $linked->firstWhere('id', $dataValueId);

        if (! $messenger) {
            // Not linked to this slot — reject silently
            return;
        }

        $content = $messenger->content ?? [];
        $oldEnabled = $content['enabled'] ?? true;
        $newEnabled = ! $oldEnabled;

        $content['enabled'] = $newEnabled;
        $messenger->update(['content' => $content]);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'messenger.toggled',
            'subject_type' => 'DataValue',
            'subject_id'   => $messenger->id,
            'old'          => ['enabled' => $oldEnabled],
            'new'          => ['enabled' => $newEnabled],
        ]);
    }

    /**
     * Load messenger DataValues whose content.linked_slot matches this slot's key
     * and whose scope matches the slot DataValue's scope.
     */
    private function loadLinkedMessengers(DataValue $slotValue): \Illuminate\Support\Collection
    {
        $messengerType = ValueType::where('code', 'messenger')->first();

        if (! $messengerType) {
            return collect();
        }

        return DataValue::where('value_type_id', $messengerType->id)
            ->where('scope_type', $slotValue->scope_type)
            ->where('scope_id', $slotValue->scope_id)
            ->get()
            ->filter(fn (DataValue $dv) => ($dv->content['linked_slot'] ?? null) === $slotValue->key);
    }

    public function render()
    {
        $value      = null;
        $slot       = null;
        $entries    = collect();
        $resolved   = null;
        $messengers = collect();

        if ($this->open && $this->dataValueId) {
            $value = DataValue::with([
                'phoneSlot.entries.phoneNumber',
                'phoneSlot',
                'geoTags',
                'type',
            ])->find($this->dataValueId);

            if ($value && $value->phoneSlot) {
                $slot       = $value->phoneSlot;
                $entries    = $slot->entries->sortBy('priority');
                $messengers = $this->loadLinkedMessengers($value);

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
            'value'      => $value,
            'slot'       => $slot,
            'entries'    => $entries,
            'resolved'   => $resolved,
            'messengers' => $messengers,
        ]);
    }
}
