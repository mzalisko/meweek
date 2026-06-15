<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\ValueType;
use App\Services\Failover\FailoverEngine;
use App\Services\Failover\ResolvedSlot;
use App\Services\Failover\SlotResolver;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Livewire\Attributes\On;
use Livewire\Component;

class SlotPanel extends Component
{
    public bool $open = false;

    public ?int $dataValueId = null;

    public string $newNumber = '';

    public ?int $editingEntryId = null;

    public string $editE164 = '';

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

    public function addNumber(): void
    {
        $this->validate([
            'newNumber' => ['required', 'regex:/^\+\d{7,15}$/'],
        ]);

        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot.entries')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $slot = $value->phoneSlot;
        $next = $slot->entries()->count()
            ? ((int) $slot->entries()->max('priority') + 1)
            : 0;

        $pn = PhoneNumber::create([
            'e164'   => $this->newNumber,
            'status' => 'active',
        ]);

        NumberEntry::create([
            'phone_slot_id'   => $slot->id,
            'phone_number_id' => $pn->id,
            'priority'        => $next,
        ]);

        app(FailoverEngine::class)->recompute($slot->fresh(), 'user');

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'number.added',
            'subject_type' => 'phone_slot',
            'subject_id'   => $slot->id,
            'new'          => ['e164' => $this->newNumber, 'priority' => $next],
        ]);

        $this->newNumber = '';
    }

    public function startEditNumber(int $entryId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot.entries.phoneNumber')->find($this->dataValueId);
        $entry = $value?->phoneSlot?->entries->firstWhere('id', $entryId);

        if (! $entry) {
            return;
        }

        $this->editingEntryId = $entryId;
        $this->editE164 = $entry->phoneNumber->e164 ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingEntryId = null;
        $this->editE164 = '';
    }

    public function saveNumber(): void
    {
        $this->validate([
            'editE164' => ['required', 'regex:/^\+\d{7,15}$/'],
        ]);

        if (! $this->dataValueId || $this->editingEntryId === null) {
            $this->cancelEdit();

            return;
        }

        $value = DataValue::with('phoneSlot.entries.phoneNumber')->find($this->dataValueId);
        $slot = $value?->phoneSlot;
        $entry = $slot?->entries->firstWhere('id', $this->editingEntryId);

        if (! $slot || ! $entry) {
            $this->cancelEdit();

            return;
        }

        $old = $entry->phoneNumber->e164;
        $entry->phoneNumber->update(['e164' => $this->editE164]);
        app(FailoverEngine::class)->recompute($slot->fresh(), 'user');

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'number.edited',
            'subject_type' => 'phone_slot',
            'subject_id'   => $slot->id,
            'old'          => ['e164' => $old],
            'new'          => ['e164' => $this->editE164],
        ]);

        $this->cancelEdit();
    }

    /** Ручне перемикання номера active|down (§6) — повернути впалий або деактивувати. */
    public function setNumberStatus(int $entryId, string $status): void
    {
        if (! in_array($status, ['active', 'down'], true) || ! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot.entries.phoneNumber')->find($this->dataValueId);
        $entry = $value?->phoneSlot?->entries->firstWhere('id', $entryId);

        if (! $entry) {
            return;
        }

        $engine = app(FailoverEngine::class);
        $status === 'active'
            ? $engine->markNumberActive($entry->phoneNumber, 'user')
            : $engine->markNumberDown($entry->phoneNumber, 'user');
    }

    public function moveUp(int $entryId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $slot    = $value->phoneSlot;
        $entries = $slot->entries()->orderBy('priority')->get();
        $current = $entries->firstWhere('id', $entryId);

        if (! $current) {
            return;
        }

        $index    = $entries->search(fn ($e) => $e->id === $current->id);
        $neighbour = $entries->get($index - 1);

        if ($index === 0 || ! $neighbour) {
            return; // Already at top — nothing to do
        }

        $this->swapEntryPriorities($current, $neighbour);

        app(FailoverEngine::class)->recompute($slot->fresh(), 'user');

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'slot.reordered',
            'subject_type' => 'phone_slot',
            'subject_id'   => $slot->id,
            'new'          => ['moved' => $entryId, 'direction' => 'up'],
        ]);
    }

    public function moveDown(int $entryId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $slot    = $value->phoneSlot;
        $entries = $slot->entries()->orderBy('priority')->get();
        $current = $entries->firstWhere('id', $entryId);

        if (! $current) {
            return;
        }

        $index     = $entries->search(fn ($e) => $e->id === $current->id);
        $neighbour = $entries->get($index + 1);

        if (! $neighbour) {
            return; // Already at bottom — nothing to do
        }

        $this->swapEntryPriorities($current, $neighbour);

        app(FailoverEngine::class)->recompute($slot->fresh(), 'user');

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'slot.reordered',
            'subject_type' => 'phone_slot',
            'subject_id'   => $slot->id,
            'new'          => ['moved' => $entryId, 'direction' => 'down'],
        ]);
    }

    public function removeNumber(int $entryId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $slot  = $value->phoneSlot;
        $entry = $slot->entries()->find($entryId);

        if (! $entry) {
            // Entry does not belong to this slot — reject silently
            return;
        }

        $e164 = $entry->phoneNumber->e164 ?? null;

        $entry->delete();

        app(FailoverEngine::class)->recompute($slot->fresh(), 'user');

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'number.removed',
            'subject_type' => 'phone_slot',
            'subject_id'   => $slot->id,
            'old'          => ['e164' => $e164],
        ]);
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

    public function save(): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $slot  = $value->phoneSlot;
        $sites = app(FailoverEngine::class)->sitesFor($slot);

        // Publish outside any DB transaction; a failed push is not fatal —
        // bridge reconciles later on its own.
        $sites->each(function ($site) {
            $pub = app(SitePayloadCompiler::class)->publish($site);
            app(BridgePublisher::class)->push($pub);
        });

        $this->dispatch('toast', message: 'Збережено → опубліковано');
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
     * Swap the priority values of two NumberEntry rows, working around the
     * unique(phone_slot_id, priority) constraint via a three-step swap:
     * move A to a temporary safe value, move B to A's old value, move A to B's old value.
     * The temp value is chosen to be guaranteed absent from the slot's priorities.
     */
    private function swapEntryPriorities(NumberEntry $a, NumberEntry $b): void
    {
        $pA = $a->priority;
        $pB = $b->priority;

        // Find a temp priority value that does not exist in this slot.
        // Using max(priority)+1 of ALL entries in the same slot guarantees no collision.
        $maxPriority = \DB::table('number_entries')
            ->where('phone_slot_id', $a->phone_slot_id)
            ->max('priority');
        $temp = (int) $maxPriority + 1;

        \DB::table('number_entries')->where('id', $a->id)->update(['priority' => $temp]);
        \DB::table('number_entries')->where('id', $b->id)->update(['priority' => $pA]);
        \DB::table('number_entries')->where('id', $a->id)->update(['priority' => $pB]);
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
