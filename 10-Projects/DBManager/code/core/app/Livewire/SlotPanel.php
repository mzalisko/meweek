<?php

namespace App\Livewire;

use App\Admin\AccessControl;
use App\Admin\PhoneNumberAssignment;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Models\Site;
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

    public array $geoTagIds = [];

    public string $emergencyNumber = '';

    public string $slotKey = '';

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

        $this->dispatch('close-messenger-panel');
        $this->dispatch('close-editor-panel');
        $this->dataValueId      = $dataValueId;
        $this->mode             = 'settings';
        $this->editingEntryId   = null;
        $this->editE164         = '';
        $this->geoTagIds        = $value->geoTags->pluck('id')->toArray();
        $this->emergencyNumber  = $value->phoneSlot->emergency_number ?? '';
        $this->slotKey          = $value->key;
        $this->open             = true;
    }

    /**
     * Перейменувати слот (ключ DataValue). Щоб нічого не зламати, перейменування
     * каскадне в межах однієї групи: разом із цим значенням оновлюються його
     * сайт-перекриття з тим самим ключем і прив'язки месенджерів (linked_slot).
     */
    public function renameSlot(): void
    {
        if (! $this->canChangeCurrentValue() || ! $this->dataValueId) {
            return;
        }

        $newKey = trim($this->slotKey);
        $this->resetErrorBag('slotKey');

        if (! preg_match('/^[a-z0-9_]+$/', $newKey)) {
            $this->addError('slotKey', 'Ключ: лише малі латинські літери, цифри та підкреслення.');

            return;
        }

        $value = DataValue::find($this->dataValueId);
        if (! $value) {
            return;
        }

        $oldKey = $value->key;
        if ($newKey === $oldKey) {
            return;
        }



        [$groupId, $siteIds] = $this->chainScope($value);

        if ($this->chainQuery($newKey, $groupId, $siteIds)->exists()) {
            $this->addError('slotKey', 'Слот із таким ключем уже існує в цій області.');

            return;
        }

        \DB::transaction(function () use ($oldKey, $newKey, $groupId, $siteIds) {
            $this->chainQuery($oldKey, $groupId, $siteIds)
                ->get()
                ->each(fn (DataValue $dv) => $dv->update(['key' => $newKey]));

            $this->relinkMessengers($oldKey, $newKey, $groupId, $siteIds);
        });

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'slot.renamed',
            'subject_type' => 'DataValue',
            'subject_id'   => $value->id,
            'old'          => ['key' => $oldKey],
            'new'          => ['key' => $newKey],
        ]);

        $this->slotKey = $newKey;

        $value->refresh()->loadMissing('phoneSlot');
        if ($value->phoneSlot) {
            $this->publishSlotSites($value->phoneSlot);
        }

        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Ключ слота змінено → опубліковано');
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
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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
        $this->mode = 'settings';
    }

    public function saveNumber(): void
    {
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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


        $entry = app(PhoneNumberAssignment::class)->assign($entry, $e164);
        app(FailoverEngine::class)->recompute($slot->fresh(), 'user');

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'number.edited',
            'subject_type' => 'DataValue',
            'subject_id'   => $value->id,
            'old'          => [
                'e164'       => $old,
                'scope_type' => $value->scope_type,
                'scope_id'   => $value->scope_id,
            ],
            'new'          => [
                'e164'       => $e164,
                'scope_type' => $value->scope_type,
                'scope_id'   => $value->scope_id,
            ],
        ]);

        $this->publishSlotSites($slot->fresh());
        $this->cancelEdit();
        $this->closeAndRefresh('Номер збережено → опубліковано');
    }

    /** Ручне перемикання номера active|down (§6) — повернути впалий або деактивувати. */
    public function setNumberStatus(int $entryId, string $status): void
    {
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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
        if (! $this->canDeleteCurrentValue()) {
            return;
        }

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
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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
        if ($slot->return_mode === $mode) {
            return;
        }



        $slot->update(['return_mode' => $mode]);
        app(FailoverEngine::class)->recompute($slot->fresh(), 'user');
        $this->dispatch('slot-updated');
    }

    public function setExhaustionPolicy(string $policy): void
    {
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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

        if ($value->phoneSlot->exhaustion_policy === $policy) {
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
        if (! $this->canChangeCurrentValue()) {
            return;
        }

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
        if (! $this->canChangeCurrentValue()) {
            return;
        }

        if (! $this->dataValueId) {
            return;
        }

        $value = DataValue::with(['phoneSlot', 'geoTags'])->find($this->dataValueId);

        if (! $value || ! $value->phoneSlot) {
            return;
        }

        $oldGeoIds = $value->geoTags->pluck('id')->sort()->values()->toArray();
        $newGeoIds = collect($this->geoTagIds)->map(fn ($id) => (int) $id)->sort()->values()->toArray();
        $slot = $value->phoneSlot;
        $newEmergency = $this->emergencyNumber ?: null;
        $geoChanged = $oldGeoIds !== $newGeoIds;
        $emergencyChanged = ($slot->emergency_number ?? null) !== $newEmergency;

        if (! $geoChanged && ! $emergencyChanged && ! $notify) {
            return;
        }



        $changed = false;

        // Sync geo-tags with audit when changed
        if ($geoChanged) {
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

        if ($emergencyChanged) {
            $slot->update(['emergency_number' => $newEmergency]);
            $changed = true;
        }

        if (! $changed && ! $notify) {
            return;
        }

        $this->publishSlotSites($slot);

        if ($notify) {
            $this->closeAndRefresh('Збережено → опубліковано');
        } else {
            $this->dispatch('slot-updated');
        }
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

    private function publishSlotSites(PhoneSlot $slot): void
    {
        app(FailoverEngine::class)->sitesFor($slot)
            ->each(function ($site) {
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
     * Межі ланцюга ключа: id групи (якщо є) і всі сайти цієї групи. Для сайту без
     * групи ланцюг — лише сам сайт. Так перейменування не виходить за межі групи.
     *
     * @return array{0:?int,1:\Illuminate\Support\Collection<int,int>}
     */
    private function chainScope(DataValue $value): array
    {
        return [null, collect([(int) $value->scope_id])];
    }

    /**
     * Усі DataValue із цим ключем у межах ланцюга (лише сам сайт).
     *
     * @param  \Illuminate\Support\Collection<int,int>  $siteIds
     */
    private function chainQuery(string $key, ?int $groupId, $siteIds)
    {
        return DataValue::where('key', $key)
            ->where('scope_type', 'site')
            ->whereIn('scope_id', $siteIds);
    }

    /**
     * Перенаправити месенджери, прив'язані до старого ключа телефону, на новий ключ.
     *
     * @param  \Illuminate\Support\Collection<int,int>  $siteIds
     */
    private function relinkMessengers(string $oldKey, string $newKey, ?int $groupId, $siteIds): void
    {
        $messengerTypeId = ValueType::where('code', 'messenger')->value('id');
        if (! $messengerTypeId) {
            return;
        }

        DataValue::where('value_type_id', $messengerTypeId)
            ->where('scope_type', 'site')
            ->whereIn('scope_id', $siteIds)
            ->get()
            ->each(function (DataValue $messenger) use ($oldKey, $newKey) {
                $content = $messenger->content ?? [];
                $linked = $content['linked_slot'] ?? null;
                $linked = is_array($linked)
                    ? $linked
                    : ($linked !== null && $linked !== '' ? [$linked] : []);

                if (! in_array($oldKey, $linked, true)) {
                    return;
                }

                $content['linked_slot'] = array_values(array_map(
                    fn ($k) => $k === $oldKey ? $newKey : $k,
                    $linked,
                ));
                $messenger->update(['content' => $content]);
            });
    }

    private function canChangeCurrentValue(): bool
    {
        if (! $this->dataValueId) {
            return false;
        }

        $value = DataValue::find($this->dataValueId);
        if (! $value) {
            return false;
        }

        $access = app(AccessControl::class);

        return $access->canEditValue(auth()->user(), $value)
            && $access->canPublishValue(auth()->user(), $value);
    }

    private function canDeleteCurrentValue(): bool
    {
        if (! $this->dataValueId) {
            return false;
        }

        $value = DataValue::find($this->dataValueId);
        if (! $value) {
            return false;
        }

        return app(AccessControl::class)->canDeleteValue(auth()->user(), $value);
    }

    public function render()
    {
        $value               = null;
        $slot                = null;
        $entries             = collect();
        $resolved            = null;
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
            'allGeoTags'          => $allGeoTags,
        ]);
    }
}
