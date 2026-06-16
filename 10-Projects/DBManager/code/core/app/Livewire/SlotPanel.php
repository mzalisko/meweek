<?php

namespace App\Livewire;

use App\Admin\AffectedSites;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Models\ValueType;
use App\Services\Failover\FailoverEngine;
use App\Services\Failover\SlotResolver;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Livewire\Attributes\On;
use Livewire\Component;

class SlotPanel extends Component
{
    public bool $open = false;

    public string $mode = 'settings';

    public ?int $dataValueId = null;

    public string $newNumber = '';

    public ?int $editingEntryId = null;

    public string $editE164 = '';

    public ?int $editingMessengerId = null;

    public string $editMessengerUrl = '';

    public string $editMessengerValue = '';

    public string $newMessengerNetwork = 'telegram';

    public string $newMessengerValue = '';

    public array $geoTagIds = [];

    public string $emergencyNumber = '';

    #[On('close-slot-panel')]
    public function closePanel(): void
    {
        $this->open = false;
        $this->cancelEdit();
    }

    #[On('open-slot')]
    public function open(int $dataValueId): void
    {
        $value = DataValue::with([
            'phoneSlot.entries.phoneNumber',
            'phoneSlot',
            'geoTags',
            'type',
        ])->find($dataValueId);

        if (! $value || ! $value->phoneSlot) {
            $this->open = false;

            return;
        }

        $this->dispatch('close-editor-panel');
        $this->dataValueId      = $dataValueId;
        $this->mode             = 'settings';
        $this->editingEntryId   = null;
        $this->editE164         = '';
        $this->editingMessengerId = null;
        $this->editMessengerUrl = '';
        $this->editMessengerValue = '';
        $this->newMessengerValue = '';
        $this->geoTagIds        = $value->geoTags->pluck('id')->toArray();
        $this->emergencyNumber  = $value->phoneSlot->emergency_number ?? '';
        $this->open             = true;
    }

    #[On('open-number-editor')]
    public function openNumberEditor(int $dataValueId, int $entryId): void
    {
        $this->open($dataValueId);
        $this->startEditNumber($entryId);

        if ($this->editingEntryId === $entryId) {
            $this->mode = 'number';
        }
    }

    public function addNumber(): void
    {
        $e164 = $this->normalizedPhoneInput('newNumber', $this->newNumber);

        if (! $e164) {
            return;
        }

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

        $pn = PhoneNumber::firstOrCreate(
            ['e164' => $e164],
            ['status' => 'active'],
        );

        if ($slot->entries()->where('phone_number_id', $pn->id)->exists()) {
            $this->addError('newNumber', 'Цей номер уже є у слоті.');

            return;
        }

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
            'new'          => ['e164' => $e164, 'priority' => $next],
        ]);

        $this->newNumber = '';
        $this->publishSlotSites($slot->fresh());
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Резерв додано → опубліковано');
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

    public function close(): void
    {
        $this->open = false;
        $this->cancelEdit();
        $this->cancelMessengerEdit();
        $this->mode = 'settings';
    }

    public function startEditMessenger(int $dataValueId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::find($this->dataValueId);
        if (! $value) {
            return;
        }

        $messengerType = ValueType::where('code', 'messenger')->first();

        $messenger = $messengerType
            ? DataValue::where('value_type_id', $messengerType->id)
                ->where('scope_type', $value->scope_type)
                ->where('scope_id', $value->scope_id)
                ->find($dataValueId)
            : null;

        if (! $messenger) {
            return;
        }

        $this->editingMessengerId = $messenger->id;
        $this->editMessengerValue = (string) ($messenger->content['value'] ?? ($messenger->content['url'] ?? ''));
        $this->editMessengerUrl = (string) ($messenger->content['url'] ?? '');
    }

    public function cancelMessengerEdit(): void
    {
        $this->editingMessengerId = null;
        $this->editMessengerUrl = '';
        $this->editMessengerValue = '';
    }

    public function saveMessengerUrl(): void
    {
        $this->saveMessengerValue();
    }

    public function saveMessengerValue(): void
    {
        if (! $this->dataValueId || $this->editingMessengerId === null) {
            $this->cancelMessengerEdit();

            return;
        }

        $value = DataValue::find($this->dataValueId);
        if (! $value) {
            $this->cancelMessengerEdit();

            return;
        }

        $messengerType = ValueType::where('code', 'messenger')->first();
        $messenger = $messengerType
            ? DataValue::where('value_type_id', $messengerType->id)
                ->where('scope_type', $value->scope_type)
                ->where('scope_id', $value->scope_id)
                ->find($this->editingMessengerId)
            : null;

        if (! $messenger) {
            $this->cancelMessengerEdit();

            return;
        }

        $content = $messenger->content ?? [];
        $oldContent = $content;
        $newValue = trim($this->editMessengerValue);

        if ($newValue === '') {
            $this->addError('editMessengerValue', 'Введіть значення месенджера.');

            return;
        }

        $content['value'] = $newValue;
        $content['url'] = $this->messengerUrlFromValue($newValue);

        if ($oldContent !== $content) {
            $messenger->update(['content' => $content]);

            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => 'messenger.value_changed',
                'subject_type' => 'DataValue',
                'subject_id'   => $messenger->id,
                'old'          => $oldContent,
                'new'          => $content,
            ]);

            $this->publishDataValue($messenger->fresh());
        }

        $this->cancelMessengerEdit();
        $this->dispatch('slot-updated');
    }

    public function addMessengerReserve(): void
    {
        $this->resetErrorBag('newMessengerValue');

        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('geoTags')->find($this->dataValueId);

        if (! $value) {
            return;
        }

        $messengerValue = trim($this->newMessengerValue);
        if ($messengerValue === '') {
            $this->addError('newMessengerValue', 'Введіть посилання, номер або код месенджера.');

            return;
        }

        $network = trim($this->newMessengerNetwork) ?: 'messenger';
        $messengerType = ValueType::firstOrCreate(['code' => 'messenger'], ['name' => 'messenger']);
        $content = [
            'value'       => $messengerValue,
            'network'     => $network,
            'url'         => $this->messengerUrlFromValue($messengerValue),
            'linked_slot' => $value->key,
            'enabled'     => true,
        ];

        $messenger = DataValue::create([
            'key'           => $this->uniqueMessengerKey($value, $network),
            'value_type_id' => $messengerType->id,
            'scope_type'    => $value->scope_type,
            'scope_id'      => $value->scope_id,
            'content'       => $content,
            'status'        => 'active',
        ]);

        $messenger->geoTags()->sync($value->geoTags->pluck('id')->all());

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'messenger.added',
            'subject_type' => 'DataValue',
            'subject_id'   => $messenger->id,
            'new'          => $content,
        ]);

        $this->newMessengerValue = '';
        $this->publishDataValue($messenger);
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Резерв месенджера додано → опубліковано');
    }

    public function saveNumber(): void
    {
        $e164 = $this->normalizedPhoneInput('editE164', $this->editE164);

        if (! $e164) {
            return;
        }

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
        $entry->phoneNumber->update(['e164' => $e164]);
        app(FailoverEngine::class)->recompute($slot->fresh(), 'user');

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'number.edited',
            'subject_type' => 'phone_slot',
            'subject_id'   => $slot->id,
            'old'          => ['e164' => $old],
            'new'          => ['e164' => $e164],
        ]);

        $this->publishSlotSites($slot->fresh());
        $this->cancelEdit();
        $this->closeAndRefresh('Номер збережено → опубліковано');
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

        $this->publishSlotSites($value->phoneSlot->fresh());
        $this->dispatch('slot-updated');
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

        $this->publishSlotSites($slot->fresh());
        $this->dispatch('slot-updated');
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

        $this->publishSlotSites($slot->fresh());
        $this->dispatch('slot-updated');
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

        $this->publishSlotSites($slot->fresh());
        $this->closeAndRefresh('Номер видалено → опубліковано');
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
        $this->publishSlotSites($slot->fresh());
        $this->dispatch('slot-updated');
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
        $this->publishSlotSites($value->phoneSlot->fresh());
        $this->dispatch('slot-updated');
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
        $this->dispatch('slot-updated');
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
        $this->dispatch('slot-updated');
    }

    public function updatedGeoTagIds(): void
    {
        $this->persistSettings(false);
    }

    public function updatedEmergencyNumber(): void
    {
        $this->persistSettings(false);
    }

    public function hideSlot(): void
    {
        $this->setSlotVisibility('hidden');
    }

    public function showSlot(): void
    {
        $this->setSlotVisibility('active');
    }

    private function setSlotVisibility(string $status): void
    {
        if (! in_array($status, ['active', 'hidden'], true) || ! $this->dataValueId) {
            return;
        }

        $value = DataValue::with('phoneSlot')->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $oldStatus = $value->status;
        if ($oldStatus === $status) {
            return;
        }

        $value->update(['status' => $status]);
        $value->refresh()->load('phoneSlot.entries.phoneNumber', 'geoTags', 'type');

        app(FailoverEngine::class)->recompute($value->phoneSlot, 'user');
        $this->publishSlotSites($value->phoneSlot);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => $status === 'hidden' ? 'slot.hidden' : 'slot.shown',
            'subject_type' => 'DataValue',
            'subject_id'   => $value->id,
            'old'          => ['status' => $oldStatus],
            'new'          => ['status' => $status],
        ]);

        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: $status === 'hidden' ? 'Слот приховано' : 'Слот показано');
    }

    public function save(): void
    {
        $this->persistSettings(true);
    }

    private function persistSettings(bool $notify): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with(['phoneSlot', 'geoTags'])->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $changed = false;

        // Sync geo-tags with audit when changed
        $oldGeoIds = $value->geoTags->pluck('id')->sort()->values()->toArray();
        $newGeoIds = collect($this->geoTagIds)->map(fn ($id) => (int) $id)->sort()->values()->toArray();
        if ($oldGeoIds !== $newGeoIds) {
            $value->geoTags()->sync($this->geoTagIds);
            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => 'value.geo_changed',
                'subject_type' => 'DataValue',
                'subject_id'   => $value->id,
                'old'          => ['geo_tag_ids' => $oldGeoIds],
                'new'          => ['geo_tag_ids' => $newGeoIds],
            ]);
            $changed = true;
        }

        $slot = $value->phoneSlot;
        $newEmergency = $this->emergencyNumber ?: null;
        if (($slot->emergency_number ?? null) !== $newEmergency) {
            $slot->update(['emergency_number' => $newEmergency]);
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $this->publishSlotSites($slot);

        if ($notify) {
            $this->closeAndRefresh('Збережено → опубліковано');
        } else {
            $this->dispatch('slot-updated');
        }
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

        $this->publishDataValue($messenger->fresh());
        $this->dispatch('slot-updated');
    }

    public function pinMessenger(int $dataValueId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::find($this->dataValueId);
        $messenger = DataValue::find($dataValueId);

        if (! $value || ! $messenger) {
            return;
        }

        $linked = $this->loadLinkedMessengers($value);
        $targetGroupKey = $messenger->content['linked_slot'] ?? $messenger->key;

        foreach ($linked as $item) {
            $content = $item->content ?? [];
            $oldPinned = (bool) ($content['pinned'] ?? false);
            $newPinned = ($item->id === $messenger->id);

            if ($oldPinned === $newPinned) {
                continue;
            }

            $content['pinned'] = $newPinned;
            $item->update(['content' => $content]);

            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => 'messenger.pinned',
                'subject_type' => 'DataValue',
                'subject_id'   => $item->id,
                'old'          => ['pinned' => $oldPinned, 'group' => $targetGroupKey],
                'new'          => ['pinned' => $newPinned, 'group' => $targetGroupKey],
            ]);
        }

        $this->publishDataValue($messenger->fresh());
        $this->dispatch('slot-updated');
    }

    public function unpinMessenger(int $dataValueId): void
    {
        $messenger = DataValue::find($dataValueId);

        if (! $messenger) {
            return;
        }

        $content = $messenger->content ?? [];
        $oldPinned = (bool) ($content['pinned'] ?? false);
        if (! $oldPinned) {
            return;
        }

        $content['pinned'] = false;
        $messenger->update(['content' => $content]);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'messenger.unpinned',
            'subject_type' => 'DataValue',
            'subject_id'   => $messenger->id,
            'old'          => ['pinned' => true],
            'new'          => ['pinned' => false],
        ]);

        $this->publishDataValue($messenger->fresh());
        $this->dispatch('slot-updated');
    }

    public function linkMessenger(int $messengerId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $slotValue = DataValue::find($this->dataValueId);
        $messenger = DataValue::find($messengerId);

        if (! $slotValue || ! $messenger) {
            return;
        }

        $content   = $messenger->content ?? [];
        $oldLinked = $content['linked_slot'] ?? null;
        $content['linked_slot'] = $slotValue->key;
        $messenger->update(['content' => $content]);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'messenger.linked',
            'subject_type' => 'DataValue',
            'subject_id'   => $messenger->id,
            'old'          => ['linked_slot' => $oldLinked],
            'new'          => ['linked_slot' => $slotValue->key],
        ]);

        $this->publishDataValue($messenger->fresh());
        $this->dispatch('slot-updated');
    }

    public function unlinkMessenger(int $messengerId): void
    {
        $messenger = DataValue::find($messengerId);

        if (! $messenger) {
            return;
        }

        $content   = $messenger->content ?? [];
        $oldLinked = $content['linked_slot'] ?? null;
        unset($content['linked_slot']);
        $messenger->update(['content' => $content]);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'messenger.unlinked',
            'subject_type' => 'DataValue',
            'subject_id'   => $messenger->id,
            'old'          => ['linked_slot' => $oldLinked],
            'new'          => ['linked_slot' => null],
        ]);

        if ($this->editingMessengerId === $messenger->id) {
            $this->cancelMessengerEdit();
        }

        $this->publishDataValue($messenger->fresh());
        $this->dispatch('slot-updated');
    }

    public function removeMessenger(int $messengerId): void
    {
        if (! $this->dataValueId) {
            return;
        }

        $slotValue = DataValue::find($this->dataValueId);
        $messenger = $slotValue ? $this->loadLinkedMessengers($slotValue)->firstWhere('id', $messengerId) : null;

        if (! $messenger) {
            return;
        }

        $old = $messenger->content ?? [];
        $affectedSites = app(AffectedSites::class)->for($messenger);
        $messenger->geoTags()->detach();
        $messenger->delete();

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'messenger.removed',
            'subject_type' => 'DataValue',
            'subject_id'   => $messengerId,
            'old'          => $old,
        ]);

        if ($this->editingMessengerId === $messengerId) {
            $this->cancelMessengerEdit();
        }

        $this->publishSites($affectedSites);
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Месенджер видалено → опубліковано');
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

    private function normalizedPhoneInput(string $field, string $value): ?string
    {
        $this->resetErrorBag($field);

        $value = trim($value);
        if (! preg_match('/^\+\d{7,15}$/', $value)) {
            $this->addError($field, 'Введіть номер у форматі +380441112233.');

            return null;
        }

        return $value;
    }

    private function messengerUrlFromValue(string $value): ?string
    {
        return preg_match('/^https?:\/\//i', $value) ? $value : null;
    }

    private function uniqueMessengerKey(DataValue $slotValue, string $network): string
    {
        $base = strtolower((string) preg_replace('/[^a-z0-9_]+/i', '_', $network . '_' . $slotValue->key));
        $base = trim($base, '_') ?: 'messenger';
        $next = 1;

        do {
            $key = $base . '_' . $next++;
        } while (DataValue::where('key', $key)
            ->where('scope_type', $slotValue->scope_type)
            ->where('scope_id', $slotValue->scope_id)
            ->exists());

        return $key;
    }

    private function publishSlotSites(PhoneSlot $slot): void
    {
        app(FailoverEngine::class)->sitesFor($slot)
            ->each(function ($site) {
                $pub = app(SitePayloadCompiler::class)->publish($site);
                app(BridgePublisher::class)->push($pub);
            });
    }

    private function publishDataValue(DataValue $value): void
    {
        $this->publishSites(app(AffectedSites::class)->for($value));
    }

    private function publishSites($sites): void
    {
        $sites->each(function ($site) {
            $pub = app(SitePayloadCompiler::class)->publish($site);
            app(BridgePublisher::class)->push($pub);
        });
    }

    private function closeAndRefresh(string $message): void
    {
        $this->close();
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: $message);
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
            ->with('geoTags', 'type')
            ->get()
            ->filter(fn (DataValue $dv) => ($dv->content['linked_slot'] ?? null) === $slotValue->key)
            ->sortBy(fn (DataValue $dv) => sprintf(
                '%d_%d_%010d_%010d',
                (bool) ($dv->content['pinned'] ?? false) ? 0 : 1,
                ($dv->status ?? 'active') === 'active' && ($dv->content['enabled'] ?? true) ? 0 : 1,
                $dv->created_at?->getTimestamp() ?? 0,
                $dv->id
            ))
            ->values();
    }

    public function render()
    {
        $value               = null;
        $slot                = null;
        $entries             = collect();
        $resolved            = null;
        $messengers          = collect();
        $availableMessengers = collect();
        $allGeoTags          = GeoTag::orderBy('code')->get();

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

                $messengerType = ValueType::where('code', 'messenger')->first();
                if ($messengerType) {
                    $availableMessengers = DataValue::where('value_type_id', $messengerType->id)
                        ->where('scope_type', $value->scope_type)
                        ->where('scope_id', $value->scope_id)
                        ->with('geoTags', 'type')
                        ->get()
                        ->filter(fn (DataValue $dv) => ($dv->content['linked_slot'] ?? null) === null);
                }

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
            'value'               => $value,
            'slot'                => $slot,
            'entries'             => $entries,
            'resolved'            => $resolved,
            'messengers'          => $messengers,
            'availableMessengers' => $availableMessengers,
            'allGeoTags'          => $allGeoTags,
        ]);
    }
}
