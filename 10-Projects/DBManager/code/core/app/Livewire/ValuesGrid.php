<?php

namespace App\Livewire;

use App\Admin\AffectedSites;
use App\Admin\AccessControl;
use App\Admin\SiteGridReader;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\NumberEntry;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\ValueType;
use App\Services\Failover\FailoverEngine;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Livewire\Attributes\On;
use Livewire\Component;

class ValuesGrid extends Component
{
    public ?int $site = null;

    public ?string $search = null;
    public ?string $type   = null;
    public ?string $geo    = null;
    public ?string $status = null;

    public array $selected = [];

    public ?int $editingPhoneEntryId = null;

    public string $editingPhoneNumber = '';

    public ?int $editingMessengerId = null;

    public string $editingMessengerValue = '';

    public array $newMessengerNetwork = [];

    public array $newMessengerValue = [];


    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selected, true)) {
            $this->selected = array_values(array_filter($this->selected, fn($v) => $v !== $id));
        } else {
            $this->selected[] = $id;
        }
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function updatedSite(): void
    {
        $this->clearSelection();
    }

    public function updatedSearch(): void
    {
        $this->clearSelection();
    }

    public function updatedType(): void
    {
        $this->clearSelection();
    }

    public function updatedGeo(): void
    {
        $this->clearSelection();
    }

    public function updatedStatus(): void
    {
        $this->clearSelection();
    }

    public function openSlot(int $dataValueId): void
    {
        $this->dispatch('close-messenger-panel');
        $this->dispatch('open-slot', dataValueId: $dataValueId);
    }

    public function openMessengerSlot(int $dataValueId): void
    {
        $this->dispatch('open-messenger-slot', dataValueId: $dataValueId);
    }

    public function editPhoneNumber(int $dataValueId, int $entryId): void
    {
        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($entryId);

        if (! $this->entryBelongsToCurrentSite($entry)) {
            return;
        }

        $this->dispatch('open-number-editor', dataValueId: $dataValueId, entryId: $entryId);
    }

    public function editValue(int $dataValueId): void
    {
        $value = DataValue::find($dataValueId);
        if (! $this->canChangeValue($value)) {
            return;
        }

        $this->dispatch('close-messenger-panel');
        $this->dispatch('edit-value', valueId: $dataValueId);
    }

    public function addValue(): void
    {
        if (! $this->ensureCanEditCurrentSite()) {
            return;
        }

        $this->dispatch('open-value-editor', siteId: $this->site);
    }

    public function startInlinePhoneEdit(int $entryId): void
    {
        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($entryId);

        if (! $this->entryBelongsToCurrentSite($entry) || ! $this->canChangeValue($entry->slot->dataValue)) {
            return;
        }

        $this->editingPhoneEntryId = $entry->id;
        $this->editingPhoneNumber = $entry->phoneNumber->e164 ?? '';
    }

    public function cancelInlinePhoneEdit(): void
    {
        $this->editingPhoneEntryId = null;
        $this->editingPhoneNumber = '';
    }

    public function startInlineMessengerEdit(int $dataValueId): void
    {
        $messenger = DataValue::with('type')->find($dataValueId);

        if (! $this->messengerBelongsToCurrentSite($messenger) || ! $this->canChangeValue($messenger)) {
            return;
        }

        $this->editingMessengerId = $messenger->id;
        $this->editingMessengerValue = (string) ($messenger->content['value'] ?? ($messenger->content['url'] ?? ''));
    }

    public function cancelInlineMessengerEdit(): void
    {
        $this->editingMessengerId = null;
        $this->editingMessengerValue = '';
    }

    public function linkMessengerToPhone(int $dataValueId, string $phoneKey): void
    {
        $messenger = DataValue::with('type')->find($dataValueId);

        if (! $this->messengerBelongsToCurrentSite($messenger) || ! $this->canDeleteValue($messenger)) {
            return;
        }

        $content = $messenger->content ?? [];
        $existing = $this->normalizeLinkedSlots($content['linked_slot'] ?? null);
        if (! in_array($phoneKey, $existing, true)) {
            $existing[] = $phoneKey;
        }
        $content['linked_slot'] = $existing;
        $messenger->update(['content' => $content]);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'messenger.linked',
            'subject_type' => 'DataValue',
            'subject_id'   => $messenger->id,
            'new'          => ['linked_slot' => $existing],
        ]);

        $this->publishDataValue($messenger->fresh());
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Месенджер прив\'язано до телефону');
    }

    public function unlinkMessengerFromPhone(int $dataValueId, string $phoneKey): void
    {
        $messenger = DataValue::with('type')->find($dataValueId);

        if (! $this->messengerBelongsToCurrentSite($messenger)) {
            return;
        }

        $content = $messenger->content ?? [];
        $old     = $this->normalizeLinkedSlots($content['linked_slot'] ?? null);
        $updated = array_values(array_filter($old, fn ($k) => $k !== $phoneKey));

        if (empty($updated)) {
            unset($content['linked_slot']);
        } else {
            $content['linked_slot'] = $updated;
        }
        $messenger->update(['content' => $content]);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'messenger.unlinked',
            'subject_type' => 'DataValue',
            'subject_id'   => $messenger->id,
            'old'          => ['linked_slot' => $old],
            'new'          => ['linked_slot' => $updated],
        ]);

        $this->publishDataValue($messenger->fresh());
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Месенджер відв\'язано від телефону');
    }

    private function normalizeLinkedSlots(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }
        if (is_string($value) && $value !== '') {
            return [$value];
        }

        return [];
    }

    public function saveInlineMessengerValue(): void
    {
        if ($this->editingMessengerId === null) {
            return;
        }

        $messenger = DataValue::with('type')->find($this->editingMessengerId);

        if (! $this->messengerBelongsToCurrentSite($messenger)) {
            $this->cancelInlineMessengerEdit();

            return;
        }

        $value = trim($this->editingMessengerValue);
        if ($value === '') {
            $this->addError('editingMessengerValue', 'Введіть значення месенджера.');

            return;
        }

        $content = $messenger->content ?? [];
        $old = $content;
        $content['value'] = $value;
        $content['url'] = $this->messengerUrlFromValue($value);

        if ($old !== $content) {
            $messenger->update(['content' => $content]);
            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => 'messenger.value_changed',
                'subject_type' => 'DataValue',
                'subject_id'   => $messenger->id,
                'old'          => $old,
                'new'          => $content,
            ]);
            $this->publishDataValue($messenger->fresh());
        }

        $this->cancelInlineMessengerEdit();
        $this->dispatch('toast', message: 'Месенджер збережено → опубліковано');
    }

    public function addMessengerReserve(int $dataValueId): void
    {
        $primary = DataValue::with(['type', 'geoTags'])->find($dataValueId);

        if (! $this->messengerBelongsToCurrentSite($primary)) {
            return;
        }

        $field = "newMessengerValue.{$dataValueId}";
        $this->resetErrorBag($field);

        $value = trim((string) ($this->newMessengerValue[$dataValueId] ?? ''));
        if ($value === '') {
            $this->addError($field, 'Введіть посилання, номер або код.');

            return;
        }

        $network = trim((string) ($this->newMessengerNetwork[$dataValueId] ?? ($primary->content['network'] ?? 'telegram'))) ?: 'telegram';
        $groupKey = $this->messengerGroupKey($primary);
        $messengerType = ValueType::firstOrCreate(['code' => 'messenger'], ['name' => 'messenger']);
        $content = [
            'value' => $value,
            'network' => $network,
            'url' => $this->messengerUrlFromValue($value),
            'messenger_slot' => $groupKey,
            'enabled' => true,
            'exhaustion_policy' => $primary->content['exhaustion_policy'] ?? 'hide',
        ];

        $reserve = DataValue::create([
            'key' => $this->uniqueMessengerKey($primary, $network),
            'value_type_id' => $messengerType->id,
            'scope_type' => $primary->scope_type,
            'scope_id' => $primary->scope_id,
            'content' => $content,
            'status' => 'active',
        ]);
        $reserve->geoTags()->sync($primary->geoTags->pluck('id')->all());

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.added',
            'subject_type' => 'DataValue',
            'subject_id' => $reserve->id,
            'new' => $content,
        ]);

        $this->newMessengerValue[$dataValueId] = '';
        $this->publishDataValue($reserve);
        $this->dispatch('toast', message: 'Резерв месенджера додано → опубліковано');
    }

    public function deactivateMessenger(int $dataValueId): void
    {
        $this->setMessengerEnabled($dataValueId, false);
    }

    public function restoreMessenger(int $dataValueId): void
    {
        $this->setMessengerEnabled($dataValueId, true);
    }

    private function setMessengerEnabled(int $dataValueId, bool $enabled): void
    {
        $messenger = DataValue::with('type')->find($dataValueId);

        if (! $this->messengerBelongsToCurrentSite($messenger)) {
            return;
        }

        $content = $messenger->content ?? [];
        $oldEnabled = $content['enabled'] ?? true;
        if ($oldEnabled === $enabled) {
            return;
        }

        $group = $this->messengerGroup($messenger);
        $groupKey = $this->messengerGroupKey($messenger);
        $content['enabled'] = $enabled;
        $messenger->update(['content' => $content]);

        $currentId = $enabled
            ? $messenger->id
            : $group
                ->filter(fn (DataValue $item) => $item->id !== $messenger->id)
                ->first(fn (DataValue $item) => ($item->status ?? 'active') === 'active' && ($item->content['enabled'] ?? true))
                ?->id;

        foreach ($group as $item) {
            $itemContent = $item->fresh()->content ?? [];
            $itemContent['current_messenger_id'] = $currentId;
            $itemContent['last_active_value'] = $content['value'] ?? ($content['name'] ?? null);
            $item->update(['content' => $itemContent]);
        }

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.toggled',
            'subject_type' => 'DataValue',
            'subject_id' => $messenger->id,
            'old' => ['enabled' => $oldEnabled, 'group' => $groupKey],
            'new' => ['enabled' => $enabled, 'current_messenger_id' => $currentId, 'group' => $groupKey],
        ]);

        $this->publishDataValue($messenger->fresh());
    }

    public function pinMessenger(int $dataValueId): void
    {
        $messenger = DataValue::with('type')->find($dataValueId);

        if (! $this->messengerBelongsToCurrentSite($messenger)) {
            return;
        }

        $group = $this->messengerGroup($messenger);
        foreach ($group as $item) {
            $content = $item->content ?? [];
            $oldPinned = (bool) ($content['pinned'] ?? false);
            $newPinned = $item->id === $messenger->id;
            if ($oldPinned === $newPinned) {
                continue;
            }
            $content['pinned'] = $newPinned;
            if ($newPinned) {
                $content['current_messenger_id'] = $messenger->id;
            }
            $item->update(['content' => $content]);
        }

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.pinned',
            'subject_type' => 'DataValue',
            'subject_id' => $messenger->id,
            'new' => ['pinned' => true],
        ]);

        $this->publishDataValue($messenger->fresh());
    }

    public function unpinMessenger(int $dataValueId): void
    {
        $messenger = DataValue::with('type')->find($dataValueId);

        if (! $this->messengerBelongsToCurrentSite($messenger)) {
            return;
        }

        $group = $this->messengerGroup($messenger);
        foreach ($group as $item) {
            $content = $item->content ?? [];
            if (! ($content['pinned'] ?? false)) {
                continue;
            }
            $content['pinned'] = false;
            $item->update(['content' => $content]);
        }

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.unpinned',
            'subject_type' => 'DataValue',
            'subject_id' => $messenger->id,
            'new' => ['pinned' => false],
        ]);

        $this->publishDataValue($messenger->fresh());
    }

    public function removeMessenger(int $dataValueId): void
    {
        $messenger = DataValue::with(['type', 'geoTags'])->find($dataValueId);

        if (! $this->messengerBelongsToCurrentSite($messenger)) {
            return;
        }

        $affectedSites = app(AffectedSites::class)->for($messenger);
        $old = $messenger->content ?? [];
        $messenger->geoTags()->detach();
        $messenger->delete();

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.removed',
            'subject_type' => 'DataValue',
            'subject_id' => $dataValueId,
            'old' => $old,
        ]);

        $this->publishSites($affectedSites);
        $this->cancelInlineMessengerEdit();
        $this->dispatch('toast', message: 'Месенджер видалено → опубліковано');
    }

    public function setMessengerExhaustionPolicy(int $dataValueId, string $policy): void
    {
        if (! in_array($policy, ['hide', 'last'], true)) {
            return;
        }

        $messenger = DataValue::with('type')->find($dataValueId);

        if (! $this->messengerBelongsToCurrentSite($messenger)) {
            return;
        }

        foreach ($this->messengerGroup($messenger) as $item) {
            $content = $item->content ?? [];
            $content['exhaustion_policy'] = $policy;
            $item->update(['content' => $content]);
        }

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.exhaustion_policy_changed',
            'subject_type' => 'DataValue',
            'subject_id' => $messenger->id,
            'new' => ['exhaustion_policy' => $policy],
        ]);

        $this->publishDataValue($messenger->fresh());
    }

    public function saveInlinePhoneNumber(): void
    {
        $e164 = $this->normalizedPhoneInput('editingPhoneNumber', $this->editingPhoneNumber);

        if (! $e164) {
            return;
        }

        if ($this->editingPhoneEntryId === null) {
            return;
        }

        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($this->editingPhoneEntryId);

        if (! $this->entryBelongsToCurrentSite($entry) || ! $this->canDeleteValue($entry->slot?->dataValue)) {
            $this->cancelInlinePhoneEdit();

            return;
        }

        $old = $entry->phoneNumber->e164;

        if ($old !== $e164) {
            $entry->phoneNumber->update(['e164' => $e164]);
            app(FailoverEngine::class)->recompute($entry->slot->fresh(), 'user');

            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => 'number.edited',
                'subject_type' => 'phone_slot',
                'subject_id'   => $entry->phone_slot_id,
                'old'          => ['e164' => $old],
                'new'          => ['e164' => $e164],
            ]);

            $this->publishSlots(collect([$entry->slot->fresh()]));
        }

        $this->cancelInlinePhoneEdit();
        $this->dispatch('toast', message: 'Номер збережено → опубліковано');
    }

    public function removeInlinePhoneNumber(int $entryId): void
    {
        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($entryId);

        if (! $this->entryBelongsToCurrentSite($entry)) {
            $this->cancelInlinePhoneEdit();

            return;
        }

        $slot = $entry->slot;
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

        $this->publishSlots(collect([$slot->fresh()]));
        $this->cancelInlinePhoneEdit();
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Номер видалено → опубліковано');
    }

    public function deactivatePhoneNumber(int $entryId): void
    {
        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($entryId);

        if (! $this->entryBelongsToCurrentSite($entry)) {
            return;
        }

        $affectedSlots = app(FailoverEngine::class)->markNumberDown($entry->phoneNumber, 'user');
        $this->publishSlots($affectedSlots->push($entry->slot->fresh()));
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Номер приховано → опубліковано');
    }

    public function restorePhoneNumber(int $entryId): void
    {
        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($entryId);

        if (! $this->entryBelongsToCurrentSite($entry)) {
            return;
        }

        $affectedSlots = app(FailoverEngine::class)->markNumberActive($entry->phoneNumber, 'user');
        $this->publishSlots($affectedSlots->push($entry->slot->fresh()));
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Номер повернуто → опубліковано');
    }

    public function pinPhoneNumber(int $entryId): void
    {
        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($entryId);

        if (! $this->entryBelongsToCurrentSite($entry) || ($entry->phoneNumber->status ?? null) !== 'active') {
            return;
        }

        app(FailoverEngine::class)->pin($entry->slot, $entry, 'user');
        $this->publishSlots(collect([$entry->slot->fresh()]));
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Номер закріплено → опубліковано');
    }

    public function unpinPhoneSlot(int $entryId): void
    {
        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($entryId);

        if (! $this->entryBelongsToCurrentSite($entry)) {
            return;
        }

        app(FailoverEngine::class)->unpin($entry->slot, 'user');
        $this->publishSlots(collect([$entry->slot->fresh()]));
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Ручний режим вимкнено → опубліковано');
    }

    #[On('slot-updated')]
    public function refreshGrid(): void
    {
        $this->cancelInlinePhoneEdit();
        $this->cancelInlineMessengerEdit();
    }

    public function mount(?int $site = null): void
    {
        $accessibleIds = app(AccessControl::class)->accessibleSiteIds(auth()->user());
        $requestedSite = $site ? (int) $site : null;

        $this->site = $requestedSite && in_array($requestedSite, $accessibleIds, true)
            ? $requestedSite
            : ($accessibleIds[0] ?? null);
    }

    public function render()
    {
        $siteModel = $this->site ? Site::with('group')->find($this->site) : null;
        if ($siteModel && ! app(AccessControl::class)->canViewSite(auth()->user(), $siteModel)) {
            $siteModel = null;
            $this->site = null;
        }

        $rows = $siteModel ? app(SiteGridReader::class)->forSite($siteModel) : [];
        $phoneKeys = collect($rows['phone'] ?? [])->pluck('key')->values()->all();
        $rows = $this->applyFilters($rows);

        $accessibleSites = app(AccessControl::class)->accessibleSites(auth()->user());
        $accessibleSiteIds = $accessibleSites->pluck('id');
        $groups = SiteGroup::whereIn('id', $accessibleSites->pluck('site_group_id')->filter()->unique())
            ->with(['sites' => fn ($query) => $query->whereIn('id', $accessibleSiteIds)->orderBy('domain')])
            ->orderBy('id')
            ->get();
        $ungroupedSites = $accessibleSites->whereNull('site_group_id')->sortBy('domain')->values();

        return view('livewire.values-grid', [
            'siteModel'       => $siteModel,
            'rows'            => $rows,
            'phoneKeys'       => $phoneKeys,
            'groups'          => $groups,
            'ungroupedSites'  => $ungroupedSites,
            'canEditCurrentSite' => $siteModel
                ? app(AccessControl::class)->canEditSite(auth()->user(), $siteModel)
                    && app(AccessControl::class)->canPublishSite(auth()->user(), $siteModel)
                : false,
        ])->layout('components.layouts.admin');
    }

    private function applyFilters(array $rows): array
    {
        // Filter by type: keep only that type group
        if ($this->type !== null && $this->type !== '') {
            $rows = isset($rows[$this->type]) ? [$this->type => $rows[$this->type]] : [];
        }

        $search = $this->search !== null ? mb_strtolower($this->search) : null;
        $geo    = $this->geo    !== null && $this->geo    !== '' ? $this->geo    : null;
        $status = $this->status !== null && $this->status !== '' ? $this->status : null;

        if ($search === null && $geo === null && $status === null) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $type => $items) {
            $kept = array_filter($items, function (array $row) use ($search, $geo, $status): bool {
                if ($search !== null && !str_contains(mb_strtolower($row['key']), $search)) {
                    return false;
                }
                if ($geo !== null && !in_array($geo, $row['geo'], true)) {
                    return false;
                }
                if ($status !== null && $row['state'] !== $status) {
                    return false;
                }
                return true;
            });

            if (!empty($kept)) {
                $filtered[$type] = array_values($kept);
            }
        }

        return $filtered;
    }

    private function entryBelongsToCurrentSite(?NumberEntry $entry): bool
    {
        if (! $entry || ! $entry->slot || ! $entry->slot->dataValue || ! $this->site) {
            return false;
        }

        $site = Site::find($this->site);
        $value = $entry->slot->dataValue;

        return $site
            && (
                ($value->scope_type === 'site' && (int) $value->scope_id === (int) $site->id)
                || ($value->scope_type === 'group' && (int) $value->scope_id === (int) $site->site_group_id)
            );
    }

    private function messengerBelongsToCurrentSite(?DataValue $value): bool
    {
        if (! $value || ! $value->type || $value->type->code !== 'messenger' || ! $this->site) {
            return false;
        }

        $site = Site::find($this->site);

        return $site
            && (
                ($value->scope_type === 'site' && (int) $value->scope_id === (int) $site->id)
                || ($value->scope_type === 'group' && (int) $value->scope_id === (int) $site->site_group_id)
            );
    }

    private function ensureCanEditCurrentSite(): bool
    {
        if (! $this->site) {
            return false;
        }

        return app(AccessControl::class)->canEditSite(auth()->user(), $this->site)
            && app(AccessControl::class)->canPublishSite(auth()->user(), $this->site);
    }

    private function canChangeValue(?DataValue $value): bool
    {
        if (! $value) {
            return false;
        }

        $access = app(AccessControl::class);

        return $access->canEditValue(auth()->user(), $value)
            && $access->canPublishValue(auth()->user(), $value);
    }

    private function canDeleteValue(?DataValue $value): bool
    {
        if (! $value) {
            return false;
        }

        return app(AccessControl::class)->canDeleteValue(auth()->user(), $value);
    }

    private function messengerGroupKey(DataValue $value): string
    {
        return $value->content['messenger_slot'] ?? $value->key;
    }

    private function messengerGroup(DataValue $value)
    {
        $messengerTypeId = ValueType::where('code', 'messenger')->value('id');
        $groupKey = $this->messengerGroupKey($value);

        return DataValue::where('value_type_id', $messengerTypeId)
            ->where('scope_type', $value->scope_type)
            ->where('scope_id', $value->scope_id)
            ->get()
            ->filter(fn (DataValue $item) => ($item->content['messenger_slot'] ?? $item->key) === $groupKey)
            ->values();
    }

    private function messengerUrlFromValue(string $value): ?string
    {
        return preg_match('/^https?:\/\//i', $value) ? $value : null;
    }

    private function uniqueMessengerKey(DataValue $primary, string $network): string
    {
        $base = strtolower((string) preg_replace('/[^a-z0-9_]+/i', '_', $network . '_' . $this->messengerGroupKey($primary)));
        $base = trim($base, '_') ?: 'messenger';
        $next = 1;

        do {
            $key = $base . '_' . $next++;
        } while (DataValue::where('key', $key)
            ->where('scope_type', $primary->scope_type)
            ->where('scope_id', $primary->scope_id)
            ->exists());

        return $key;
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

    private function publishSlots($slots): void
    {
        collect($slots)
            ->filter()
            ->unique('id')
            ->flatMap(fn ($slot) => app(FailoverEngine::class)->sitesFor($slot->fresh()))
            ->unique('id')
            ->each(function (Site $site) {
                $publication = app(SitePayloadCompiler::class)->publish($site);
                app(BridgePublisher::class)->push($publication);
            });
    }

    private function publishDataValue(DataValue $value): void
    {
        $this->publishSites(app(AffectedSites::class)->for($value));
    }

    private function publishSites($sites): void
    {
        $sites->unique('id')->each(function (Site $site) {
            $publication = app(SitePayloadCompiler::class)->publish($site);
            app(BridgePublisher::class)->push($publication);
        });
    }
}
