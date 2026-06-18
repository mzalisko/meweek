<?php

namespace App\Livewire;

use App\Admin\AffectedSites;
use App\Admin\AccessControl;
use App\Admin\PhoneNumberAssignment;
use App\Admin\PhoneSlotInheritance;
use App\Admin\SiteGridReader;
use App\Admin\SiteHierarchy;
use App\Admin\ValueScope;
use App\Livewire\Concerns\HandlesScopeDecision;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\NumberEntry;
use App\Models\PhoneSlot;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\ValueType;
use App\Services\Failover\FailoverEngine;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class ValuesGrid extends Component
{
    use HandlesScopeDecision;

    public ?int $site = null;

    public ?int $group = null;

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

    public bool $bulkReplaceOpen = false;

    public string $bulkFind = '';

    public string $bulkReplace = '';

    public string $bulkScope = 'current_site';

    public array $bulkPreview = [];

    public ?array $bulkReport = null;


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

    public function openBulkReplace(): void
    {
        $this->bulkReplaceOpen = true;
        $this->bulkFind = '';
        $this->bulkReplace = '';
        $this->bulkScope = 'current_site';
        $this->bulkPreview = [];
        $this->bulkReport = null;
        $this->resetValidation();
        $this->refreshBulkPreview();
    }

    public function closeBulkReplace(): void
    {
        $this->bulkReplaceOpen = false;
        $this->bulkFind = '';
        $this->bulkReplace = '';
        $this->bulkPreview = [];
        $this->bulkReport = null;
        $this->resetValidation();
    }

    public function updatedBulkFind(): void
    {
        $this->refreshBulkPreview();
    }

    public function updatedBulkReplace(): void
    {
        $this->refreshBulkPreview();
    }

    public function updatedBulkScope(): void
    {
        $this->refreshBulkPreview();
    }

    public function updatedGroup(mixed $value = null): void
    {
        $this->selectBreadcrumbGroup($value);
    }

    public function updatedSite(mixed $value = null): void
    {
        $this->selectBreadcrumbSite($value);
    }

    public function switchSite(?int $siteId): void
    {
        $this->selectBreadcrumbSite($siteId);
    }

    public function selectBreadcrumbGroup(mixed $groupId = null): void
    {
        $groupId = $groupId ? (int) $groupId : null;
        $accessibleGroupIds = app(AccessControl::class)
            ->accessibleSites(auth()->user())
            ->pluck('site_group_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $this->group = $groupId && in_array($groupId, $accessibleGroupIds, true)
            ? $groupId
            : null;
        $this->site = null;
        $this->clearSelection();
    }

    public function selectBreadcrumbSite(mixed $siteId = null): void
    {
        $siteId = $siteId ? (int) $siteId : null;
        $site = $siteId ? Site::find($siteId) : null;

        if (! $site || ! app(AccessControl::class)->canViewSite(auth()->user(), $site)) {
            $this->site = null;
            $this->clearSelection();

            return;
        }

        $this->site = (int) $site->id;
        $this->group = $site->site_group_id ? (int) $site->site_group_id : null;
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

    private function refreshBulkPreview(): void
    {
        if (! $this->bulkReplaceOpen) {
            return;
        }

        $find = trim($this->bulkFind);
        if ($find === '') {
            $this->bulkPreview = [];
            return;
        }

        $this->bulkPreview = $this->bulkTargetSites()
            ->flatMap(function (Site $site) use ($find): array {
                return DataValue::where('scope_type', 'site')
                    ->where('scope_id', $site->id)
                    ->get()
                    ->map(function (DataValue $value) use ($site, $find) {
                        $hits = $this->countBulkHits($value, $find);
                        return $hits > 0 ? [
                            'site' => $site->domain,
                            'id' => $value->id,
                            'key' => $value->key,
                            'type' => $value->type?->code ?? 'unknown',
                            'hits' => $hits,
                        ] : null;
                    })
                    ->filter()
                    ->values()
                    ->all();
            })
            ->all();
    }

    public function applyBulkReplace(): void
    {
        if (! $this->canRunBulkReplace()) {
            return;
        }

        $find = trim($this->bulkFind);
        if ($find === '') {
            $this->addError('bulkFind', 'Введіть текст для пошуку.');

            return;
        }

        $sites = $this->bulkTargetSites();
        $report = ['sites' => 0, 'values' => 0, 'changes' => 0];

        DB::transaction(function () use ($sites, $find, &$report): void {
            foreach ($sites as $site) {
                $report['sites']++;
                $values = DataValue::with('geoTags')
                    ->where('scope_type', 'site')
                    ->where('scope_id', $site->id)
                    ->get();

                $changed = false;
                foreach ($values as $value) {
                    $content = $value->content ?? [];
                    $newKey = str_replace($find, $this->bulkReplace, $value->key, $countKey);
                    $newContent = $this->replaceRecursively($content, $find, $this->bulkReplace, $countContent);

                    if ($countKey === 0 && $countContent === 0) {
                        continue;
                    }

                    $report['values']++;
                    $report['changes'] += $countKey + $countContent;
                    $value->key = $newKey;
                    $value->content = $newContent;
                    $value->save();
                    $changed = true;
                }

                if ($changed) {
                    $this->publishSites(collect([$site]));
                }
            }
        });

        $this->bulkReport = $report;
        $this->refreshBulkPreview();
        $this->dispatch('toast', message: 'Масову заміну застосовано');
    }

    public function openSlot(int $dataValueId): void
    {
        $value = $this->materializeDataValueForCurrentSite(
            DataValue::with(['type', 'geoTags', 'phoneSlot.entries.phoneNumber'])->find($dataValueId),
        );

        if (! $value || ! $this->canChangeValue($value)) {
            return;
        }

        $this->dispatch('close-messenger-panel');
        $this->dispatch('open-slot', dataValueId: $value->id);
    }

    public function openMessengerSlot(int $dataValueId): void
    {
        $value = $this->materializeDataValueForCurrentSite(
            DataValue::with(['type', 'geoTags'])->find($dataValueId),
        );

        if (! $value || ! $this->canChangeValue($value)) {
            return;
        }

        $this->dispatch('open-messenger-slot', dataValueId: $value->id);
    }

    public function editPhoneNumber(int $dataValueId, int $entryId): void
    {
        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($entryId);
        $entry = $this->materializePhoneEntryForCurrentSite($entry);

        if (! $entry || ! $this->canChangeValue($entry->slot?->dataValue)) {
            return;
        }

        $this->dispatch('open-number-editor', dataValueId: $entry->slot->data_value_id, entryId: $entry->id);
    }

    public function editValue(int $dataValueId): void
    {
        $value = $this->materializeDataValueForCurrentSite(DataValue::with(['type', 'geoTags'])->find($dataValueId));
        if (! $this->canChangeValue($value)) {
            return;
        }

        $this->dispatch('close-messenger-panel');
        $this->dispatch('edit-value', valueId: $value->id);
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
        $entry = $this->materializePhoneEntryForCurrentSite($entry);

        if (! $entry || ! $this->canChangeValue($entry->slot->dataValue)) {
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
        $messenger = $this->materializeMessengerForCurrentSite($messenger);

        if (! $messenger || ! $this->canChangeValue($messenger)) {
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
        $messenger = $this->materializeMessengerForCurrentSite($messenger);

        if (! $messenger || ! $this->canDeleteValue($messenger)) {
            return;
        }

        if ($this->deferForScope('linkMessengerToPhone', [$dataValueId, $phoneKey], $messenger)) {
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
        $messenger = $this->materializeMessengerForCurrentSite($messenger);

        if (! $messenger) {
            return;
        }

        if ($this->deferForScope('unlinkMessengerFromPhone', [$dataValueId, $phoneKey], $messenger)) {
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

        if ($this->deferForScope('saveInlineMessengerValue', [], $messenger)) {
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
        $primary = $this->materializeMessengerForCurrentSite($primary);

        if (! $primary) {
            return;
        }

        $field = "newMessengerValue.{$dataValueId}";
        $this->resetErrorBag($field);

        $value = trim((string) ($this->newMessengerValue[$dataValueId] ?? ''));
        if ($value === '') {
            $this->addError($field, 'Введіть посилання, номер або код.');

            return;
        }

        if ($this->deferForScope('addMessengerReserve', [$dataValueId], $primary)) {
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
        $messenger = $this->materializeMessengerForCurrentSite($messenger);

        if (! $messenger) {
            return;
        }

        $content = $messenger->content ?? [];
        $oldEnabled = $content['enabled'] ?? true;
        if ($oldEnabled === $enabled) {
            return;
        }

        if ($this->deferForScope('setMessengerEnabled', [$dataValueId, $enabled], $messenger)) {
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
        if ($messenger && $messenger->scope_type !== 'group') {
            $messenger = $this->materializeMessengerForCurrentSite($messenger);
        }

        if (! $messenger) {
            return;
        }

        if ($this->deferForScope('pinMessenger', [$dataValueId], $messenger)) {
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
        if ($messenger && $messenger->scope_type !== 'group') {
            $messenger = $this->materializeMessengerForCurrentSite($messenger);
        }

        if (! $messenger) {
            return;
        }

        if ($this->deferForScope('unpinMessenger', [$dataValueId], $messenger)) {
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
        $messenger = $this->materializeMessengerForCurrentSite($messenger);

        if (! $this->messengerBelongsToCurrentSite($messenger)) {
            return;
        }

        if ($this->deferForScope('removeMessenger', [$dataValueId], $messenger)) {
            return;
        }

        $dataValueId = $messenger->id;

        $group = $this->messengerGroup($messenger);
        $affectedSites = app(AffectedSites::class)->for($messenger);
        $old = \App\Services\Audit\AuditRestorer::serializeValue($messenger);
        $deletedId = $messenger->id;
        $messenger->geoTags()->detach();
        $messenger->delete();

        $remaining = $group->reject(fn (DataValue $item) => $item->id === $deletedId)->values();
        if ($remaining->isNotEmpty()) {
            $newCurrent = $remaining
                ->first(fn (DataValue $item) => (bool) ($item->content['pinned'] ?? false)
                    && ($item->status ?? 'active') === 'active'
                    && ($item->content['enabled'] ?? true))
                ?? $remaining->first(fn (DataValue $item) => ($item->status ?? 'active') === 'active'
                    && ($item->content['enabled'] ?? true));

            $newCurrentId = $newCurrent?->id;

            foreach ($remaining as $item) {
                $content = $item->fresh()->content ?? [];
                $content['current_messenger_id'] = $newCurrentId;
                $item->update(['content' => $content]);
            }
        }

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.removed',
            'subject_type' => 'DataValue',
            'subject_id' => $dataValueId,
            'old' => $old,
        ]);

        $this->publishSites($affectedSites);
        $this->cancelInlineMessengerEdit();
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Месенджер видалено і опубліковано');
    }

    /**
     * Прибрати слот із поточного сайту.
     * Власний слот сайту видаляється повністю (значення + телефонний слот + номери —
     * через FK-каскад). Успадкований слот (з групи або сайта-джерела) глушиться
     * tombstone-рядком (status='suppressed') лише на цьому сайті та його сателітах;
     * джерело лишається недоторканим. І навпаки: видалення на джерелі не чіпає власні
     * оверайди сателітів.
     */
    public function removeSlotFromSite(int $dataValueId): void
    {
        if (! $this->site) {
            return;
        }

        $site = Site::find($this->site);
        $value = DataValue::with('type')->find($dataValueId);

        if (! $site || ! $value) {
            return;
        }

        $isMessenger = $value->type?->code === 'messenger';
        $key = $isMessenger ? $this->messengerGroupKey($value) : $value->key;

        $ownRows = $this->ownSlotRows($site, $key, $isMessenger);

        if ($ownRows->isNotEmpty()) {
            if ($ownRows->contains(fn (DataValue $row) => ! $this->canDeleteValue($row))) {
                return;
            }

            if ($this->deferForScope('removeSlotFromSite', [$dataValueId], $ownRows->first())) {
                return;
            }

            $this->deleteOwnSlot($site, $ownRows, $key, $isMessenger);
        } else {
            if (! $this->ensureCanEditCurrentSite()) {
                return;
            }

            $this->suppressSlot($site, $value, $key);
        }

        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Слот прибрано з цього сайту → опубліковано');
    }

    public function toggleSlotVisibility(int $dataValueId): void
    {
        $value = DataValue::find($dataValueId);
        if (! $value) {
            return;
        }

        $value = $this->materializeDataValueForCurrentSite($value);
        if (! $value || ! $this->canChangeValue($value)) {
            $this->dispatch('toast', message: 'Помилка доступу');
            return;
        }

        $oldStatus = $value->status ?? 'active';
        $newStatus = $oldStatus === 'hidden' ? 'active' : 'hidden';

        if ($this->deferForScope('toggleSlotVisibility', [$dataValueId], $value)) {
            return;
        }

        $valueType = $value->type->code;

        if ($valueType === 'phone') {
            $value->update(['status' => $newStatus]);
            $value->refresh()->load('phoneSlot');
            if ($value->phoneSlot) {
                app(FailoverEngine::class)->recompute($value->phoneSlot, 'user');
                $this->publishSlots(collect([$value->phoneSlot]));
            }

            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => $newStatus === 'hidden' ? 'slot.hidden' : 'slot.shown',
                'subject_type' => 'DataValue',
                'subject_id'   => $value->id,
                'old'          => ['status' => $oldStatus],
                'new'          => ['status' => $newStatus],
            ]);
        } elseif ($valueType === 'messenger') {
            $groupKey = $value->messenger_slot ?? $value->key;
            $items = DataValue::where('scope_type', $value->scope_type)
                ->where('scope_id', $value->scope_id)
                ->where('value_type_id', $value->value_type_id)
                ->where(function ($q) use ($groupKey) {
                    $q->where('messenger_slot', $groupKey)->orWhere('key', $groupKey);
                })
                ->get();

            $affectedSites = app(AffectedSites::class)->for($value);
            foreach ($items as $item) {
                $oldItemStatus = $item->status ?? 'active';
                if ($oldItemStatus === $newStatus) {
                    continue;
                }
                $item->update(['status' => $newStatus]);
                AuditLog::create([
                    'actor_type' => 'user',
                    'action' => $newStatus === 'hidden' ? 'messenger.slot_hidden' : 'messenger.slot_shown',
                    'subject_type' => 'DataValue',
                    'subject_id' => $item->id,
                    'old' => ['status' => $oldItemStatus],
                    'new' => ['status' => $newStatus],
                ]);
            }
            $this->publishSites($affectedSites);
        } else {
            $value->update(['status' => $newStatus]);

            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => $newStatus === 'hidden' ? 'value.frozen' : 'value.updated',
                'subject_type' => 'DataValue',
                'subject_id'   => $value->id,
                'old'          => ['status' => $oldStatus],
                'new'          => ['status' => $newStatus],
            ]);
            $this->publishDataValue($value);
        }

        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: $newStatus === 'hidden' ? 'Слот приховано' : 'Слот показано');
    }

    public function setMessengerExhaustionPolicy(int $dataValueId, string $policy): void
    {
        if (! in_array($policy, ['hide', 'last'], true)) {
            return;
        }

        $messenger = DataValue::with('type')->find($dataValueId);
        $messenger = $this->materializeMessengerForCurrentSite($messenger);

        if (! $messenger) {
            return;
        }

        if ($this->deferForScope('setMessengerExhaustionPolicy', [$dataValueId, $policy], $messenger)) {
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
        $entry = $this->materializePhoneEntryForCurrentSite($entry);

        if (! $entry || ! $this->entryBelongsToCurrentSite($entry) || ! $this->canDeleteValue($entry->slot?->dataValue)) {
            $this->cancelInlinePhoneEdit();

            return;
        }

        $old = $entry->phoneNumber->e164;

        if ($old !== $e164) {
            // Форк-якщо-спільний: PhoneNumber глобальний за унікальним e164. Якщо цей
            // номер ділять кілька записів (успадкована копія сателіта розділяє номер
            // з предком), правка на місці змінила б його всюди — тож відвʼязуємо цей
            // запис на власний PhoneNumber (наявний за e164 або новий).
            if ($this->deferForScope('saveInlinePhoneNumber', [], $entry->slot?->dataValue)) {
                return;
            }

            $entry = app(PhoneNumberAssignment::class)->assign($entry, $e164);
            app(FailoverEngine::class)->recompute($entry->slot->fresh(), 'user');

            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => 'number.edited',
                'subject_type' => 'DataValue',
                'subject_id'   => $entry->slot?->data_value_id,
                'old'          => [
                    'e164'       => $old,
                    'scope_type' => $entry->slot?->dataValue?->scope_type,
                    'scope_id'   => $entry->slot?->dataValue?->scope_id,
                ],
                'new'          => [
                    'e164'       => $e164,
                    'scope_type' => $entry->slot?->dataValue?->scope_type,
                    'scope_id'   => $entry->slot?->dataValue?->scope_id,
                ],
            ]);

            $this->publishSlots(collect([$entry->slot->fresh()]));
        }

        $this->cancelInlinePhoneEdit();
        $this->dispatch('toast', message: 'Номер збережено → опубліковано');
    }

    public function removeInlinePhoneNumber(int $entryId): void
    {
        $entry = NumberEntry::with(['slot.dataValue', 'phoneNumber'])->find($entryId);
        $entry = $this->materializePhoneEntryForCurrentSite($entry);

        if (! $entry || ! $this->entryBelongsToCurrentSite($entry) || ! $this->canDeleteValue($entry->slot?->dataValue)) {
            $this->cancelInlinePhoneEdit();

            return;
        }

        if ($this->deferForScope('removeInlinePhoneNumber', [$entryId], $entry->slot?->dataValue)) {
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
        $entry = $this->materializePhoneEntryForCurrentSite($entry);

        if (! $entry) {
            return;
        }

        if ($this->deferForScope('deactivatePhoneNumber', [$entryId], $entry->slot?->dataValue)) {
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
        $entry = $this->materializePhoneEntryForCurrentSite($entry);

        if (! $entry) {
            return;
        }

        if ($this->deferForScope('restorePhoneNumber', [$entryId], $entry->slot?->dataValue)) {
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

        // Статус номера глобальний (e164 унікальний) — перевіряємо до матеріалізації,
        // щоб не плодити копію заради no-op закріплення неактивного номера.
        if (! $entry || ($entry->phoneNumber->status ?? null) !== 'active') {
            return;
        }

        $entry = $this->materializePhoneEntryForCurrentSite($entry);
        if (! $entry) {
            return;
        }

        if ($this->deferForScope('pinPhoneNumber', [$entryId], $entry->slot?->dataValue)) {
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
        $entry = $this->materializePhoneEntryForCurrentSite($entry);

        if (! $entry) {
            return;
        }

        if ($this->deferForScope('unpinPhoneSlot', [$entryId], $entry->slot?->dataValue)) {
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

    #[On('value-saved')]
    public function refreshAfterValueSaved(): void
    {
        $this->refreshGrid();
    }

    public function mount(?int $site = null): void
    {
        $accessibleIds = app(AccessControl::class)->accessibleSiteIds(auth()->user());
        $requestedSite = $site ? (int) $site : (request()->integer('site') ?: null);
        $requestedGroup = request()->integer('group') ?: null;

        $this->site = $requestedSite && in_array($requestedSite, $accessibleIds, true)
            ? $requestedSite
            : null;
        $this->group = $requestedGroup;

        // Жодного валідного сайту й без фільтра групи: якщо доступний рівно один
        // сайт — відкриваємо його одразу. Порожня сторінка на єдиному сайті (зокрема
        // при переході «Керувати даними») — це баг. За кількох сайтів лишаємо вибір.
        if (! $this->site && ! $requestedGroup && count($accessibleIds) === 1) {
            $this->site = (int) reset($accessibleIds);
        }

        if ($this->site && ! $this->group) {
            $site = Site::find($this->site);
            $this->group = $site?->site_group_id ? (int) $site->site_group_id : null;
        }
    }

    public function render()
    {
        $siteModel = $this->site ? Site::with('group')->find($this->site) : null;
        if ($siteModel && ! app(AccessControl::class)->canViewSite(auth()->user(), $siteModel)) {
            $siteModel = null;
            $this->site = null;
        }
        if ($siteModel && $this->group && (int) $siteModel->site_group_id !== (int) $this->group) {
            $siteModel = null;
            $this->site = null;
        }

        $rows = $siteModel ? app(SiteGridReader::class)->forSite($siteModel) : [];

        // Приховати цінові слоти, якщо користувач не має дозволу can_view_prices
        if (! app(AccessControl::class)->canViewPrices(auth()->user(), $siteModel)) {
            unset($rows['price']);
        }

        $phoneKeys = collect($rows['phone'] ?? [])->pluck('key')->values()->all();
        $rows = $this->applyFilters($rows);

        $accessibleSites = app(AccessControl::class)->accessibleSites(auth()->user());
        $accessibleSiteIds = $accessibleSites->pluck('id');
        $groups = SiteGroup::whereIn('id', $accessibleSites->pluck('site_group_id')->filter()->unique())
            ->with(['sites' => fn ($query) => $query->whereIn('id', $accessibleSiteIds)->orderBy('domain')])
            ->orderBy('id')
            ->get();
        $selectedGroupId = $this->group ?? $siteModel?->group?->id;
        $selectedGroup = $selectedGroupId ? $groups->firstWhere('id', (int) $selectedGroupId) : null;
        $selectedGroupSites = $selectedGroup?->sites ?? collect();
        $ungroupedSites = $accessibleSites->whereNull('site_group_id')->sortBy('domain')->values();

        return view('livewire.values-grid', [
            'siteModel'       => $siteModel,
            'rows'            => $rows,
            'phoneKeys'       => $phoneKeys,
            'groups'          => $groups,
            'selectedGroup'   => $selectedGroup,
            'selectedGroupSites' => $selectedGroupSites,
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
                if ($search !== null) {
                    $haystack = mb_strtolower($row['key']);

                    // Текст/ціна: content['value']
                    if (isset($row['value']) && $row['value'] !== null) {
                        $haystack .= ' ' . mb_strtolower((string) $row['value']);
                    }

                    // URL
                    if (!empty($row['url'])) {
                        $haystack .= ' ' . mb_strtolower($row['url']);
                    }

                    // Номери телефонів (e164)
                    if (!empty($row['numbers'])) {
                        foreach ($row['numbers'] as $num) {
                            if (!empty($num['e164'])) {
                                $haystack .= ' ' . mb_strtolower($num['e164']);
                            }
                        }
                    }

                    // Месенджери (value)
                    if (!empty($row['messengers'])) {
                        foreach ($row['messengers'] as $msg) {
                            if (!empty($msg['value'])) {
                                $haystack .= ' ' . mb_strtolower($msg['value']);
                            }
                        }
                    }

                    if (!str_contains($haystack, $search)) {
                        return false;
                    }
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

    private function materializeDataValueForCurrentSite(?DataValue $value): ?DataValue
    {
        return $value;
    }

    private function valueBelongsToCurrentSite(?DataValue $value): bool
    {
        if (! $value || ! $this->site) {
            return false;
        }

        $site = Site::find($this->site);

        return $site
            && (
                $value->scope_type === 'site' && (int) $value->scope_id === (int) $site->id
            );
    }

    private function canMaterializeValueFromSource(DataValue $value, Site $site): bool
    {
        if ($value->scope_type === 'group') {
            return $site->site_group_id !== null
                && (int) $value->scope_id === (int) $site->site_group_id;
        }

        return $value->scope_type === 'site'
            && in_array((int) $value->scope_id, app(SiteHierarchy::class)->ancestorIds($site), true);
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
                $value->scope_type === 'site' && (int) $value->scope_id === (int) $site->id
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
                $value->scope_type === 'site' && (int) $value->scope_id === (int) $site->id
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

    /**
     * Власні (site-scoped) рядки поточного сайту для ключа слота.
     * Для месенджера збирає всю групу за груповим ключем.
     *
     * @return \Illuminate\Support\Collection<int, DataValue>
     */
    private function ownSlotRows(Site $site, string $key, bool $isMessenger)
    {
        $query = DataValue::with('type')
            ->whereIn('status', ['active', 'hidden'])
            ->where('scope_type', 'site')
            ->where('scope_id', $site->id);

        if ($isMessenger) {
            return $query->get()
                ->filter(fn (DataValue $row) => $row->type?->code === 'messenger'
                    && ($row->content['messenger_slot'] ?? $row->key) === $key)
                ->values();
        }

        return $query->where('key', $key)->get();
    }

    /**
     * Видалити власний слот сайту повністю. FK-каскад прибирає phone_slot і number_entries.
     * Якщо після видалення лишається успадковане значення того ж ключа — ставимо tombstone,
     * щоб слот не «виринув» назад на цьому сайті.
     *
     * @param \Illuminate\Support\Collection<int, DataValue> $ownRows
     */
    private function deleteOwnSlot(Site $site, $ownRows, string $key, bool $isMessenger): void
    {
        $sourceTypeId = (int) $ownRows->first()->value_type_id;
        $affected = collect();

        foreach ($ownRows as $row) {
            $affected = $affected->merge(app(AffectedSites::class)->for($row));
            $old = \App\Services\Audit\AuditRestorer::serializeValue($row);
            $rowId = $row->id;
            $row->geoTags()->detach();
            $row->delete();

            AuditLog::create([
                'actor_type' => 'user',
                'action' => 'slot.removed',
                'subject_type' => 'DataValue',
                'subject_id' => $rowId,
                'old' => $old,
            ]);
        }

        if ($this->inheritedValueExists($site, $key, $isMessenger)) {
            $tombstone = $this->createSuppressionRow($site, $key, $sourceTypeId);
            $affected = $affected->merge(app(AffectedSites::class)->for($tombstone));
        }

        $this->publishSites($affected);
    }

    /**
     * Приглушити успадкований слот на поточному сайті (tombstone), не чіпаючи джерело.
     */
    private function suppressSlot(Site $site, DataValue $value, string $key): void
    {
        $tombstone = $this->createSuppressionRow($site, $key, (int) $value->value_type_id);

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'slot.suppressed',
            'subject_type' => 'DataValue',
            'subject_id' => $tombstone->id,
            'new' => ['key' => $key, 'scope_id' => $site->id],
        ]);

        $this->publishSites(app(AffectedSites::class)->for($tombstone));
    }

    /**
     * Чи існує успадковане активне значення цього ключа (з групи сайту або предка)?
     */
    private function inheritedValueExists(Site $site, string $key, bool $isMessenger): bool
    {
        $ancestorIds = app(SiteHierarchy::class)->ancestorIds($site);
        $hasGroup = (bool) $site->site_group_id;

        if ($ancestorIds === [] && ! $hasGroup) {
            return false;
        }

        $candidates = DataValue::with('type')
            ->where('status', 'active')
            ->where(function ($q) use ($ancestorIds, $hasGroup, $site) {
                if ($ancestorIds !== []) {
                    $q->orWhere(function ($qq) use ($ancestorIds) {
                        $qq->where('scope_type', 'site')->whereIn('scope_id', $ancestorIds);
                    });
                }
                if ($hasGroup) {
                    $q->orWhere(function ($qq) use ($site) {
                        $qq->where('scope_type', 'group')->where('scope_id', $site->site_group_id);
                    });
                }
            })
            ->get();

        if ($isMessenger) {
            return $candidates->contains(fn (DataValue $dv) => $dv->type?->code === 'messenger'
                && ($dv->content['messenger_slot'] ?? $dv->key) === $key);
        }

        return $candidates->contains(fn (DataValue $dv) => $dv->key === $key);
    }

    /**
     * Створити (або перевикористати) tombstone-рядок глушіння для (сайт, ключ).
     * Унікальний індекс (key, scope_type, scope_id) гарантує один рядок на ключ.
     */
    private function createSuppressionRow(Site $site, string $key, int $sourceTypeId): DataValue
    {
        $existing = DataValue::where('scope_type', 'site')
            ->where('scope_id', $site->id)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if (($existing->status ?? 'active') !== 'suppressed') {
                $existing->update(['status' => 'suppressed', 'content' => []]);
            }

            return $existing;
        }

        return DataValue::create([
            'value_type_id' => $sourceTypeId,
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'key' => $key,
            'status' => 'suppressed',
            'content' => [],
        ]);
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

    /**
     * Copy-on-write для месенджера на поточному сайті.
     *
     * Якщо значення вже редаговане на місці (власне site-перекриття поточного
     * сайту або значення його групи) — повертаємо його без змін, поведінка не
     * міняється. Якщо ж рядок успадкований із сайту-предка (сателіт) — спершу
     * матеріалізуємо власну копію всієї групи на поточному сайті (ті самі ключі,
     * content і гео-мітки) і повертаємо локальну копію, що відповідає клікнутому
     * рядку. null — якщо немає сайту/прав або значення не є успадкуванням з предка.
     */
    private function materializeMessengerForCurrentSite(?DataValue $messenger): ?DataValue
    {
        if (! $messenger || ! $messenger->type || $messenger->type->code !== 'messenger' || ! $this->site) {
            return null;
        }

        if ($this->messengerBelongsToCurrentSite($messenger)) {
            return $messenger;
        }

        $site = Site::find($this->site);
        if (! $site) {
            return null;
        }

        // Матеріалізувати дозволено лише рядок, реально успадкований із предка.
        if (! $this->canMaterializeValueFromSource($messenger, $site)) {
            return null;
        }

        if (! $this->ensureCanEditCurrentSite()) {
            return null;
        }

        return $this->copyMessengerGroupToCurrentSite($messenger, $site);
    }

    /**
     * Скопіювати всю групу месенджера (основний + резерви) на поточний сайт як
     * site-перекриття. Повертає копію, що відповідає клікнутому рядку.
     */
    private function copyMessengerGroupToCurrentSite(DataValue $messenger, Site $site): ?DataValue
    {
        $group = $this->messengerGroup($messenger);
        $clickedKey = $messenger->key;
        $local = null;

        DB::transaction(function () use ($group, $site, $clickedKey, &$local): void {
            foreach ($group as $source) {
                $copy = DataValue::create([
                    'key' => $source->key,
                    'value_type_id' => $source->value_type_id,
                    'scope_type' => 'site',
                    'scope_id' => $site->id,
                    'content' => $source->content,
                    'status' => $source->status ?? 'active',
                ]);
                $copy->geoTags()->sync($source->geoTags->pluck('id')->all());

                if ($source->key === $clickedKey) {
                    $local = $copy;
                }
            }
        });

        if ($local) {
            AuditLog::create([
                'actor_type' => 'user',
                'action' => 'messenger.materialized',
                'subject_type' => 'DataValue',
                'subject_id' => $local->id,
                'new' => ['scope_id' => $site->id, 'messenger_slot' => $this->messengerGroupKey($local)],
            ]);
        }

        return $local;
    }

    /**
     * Copy-on-write для телефонного запису на поточному сайті.
     *
     * Якщо запис уже належить поточному сайту (власне site-перекриття або слот
     * групи) — повертаємо його без змін, поведінка не міняється. Якщо ж рядок
     * успадкований із сайту-предка (сателіт) — матеріалізуємо власну копію слота
     * (DataValue + PhoneSlot + усі NumberEntry, що ділять глобальні PhoneNumber) і
     * повертаємо запис копії, який відповідає клікнутому. null — якщо немає сайту/
     * прав або значення не є успадкуванням з предка.
     */
    private function materializePhoneEntryForCurrentSite(?NumberEntry $entry): ?NumberEntry
    {
        if (! $entry || ! $entry->slot || ! $entry->slot->dataValue || ! $this->site) {
            return null;
        }

        if ($this->entryBelongsToCurrentSite($entry)) {
            return $entry;
        }

        $site = Site::find($this->site);
        $value = $entry->slot->dataValue;
        if (! $site) {
            return null;
        }

        // Матеріалізувати дозволено лише рядок, реально успадкований із предка.
        if (! $this->canMaterializeValueFromSource($value, $site)) {
            return null;
        }

        if (! $this->ensureCanEditCurrentSite()) {
            return null;
        }

        return $this->copyPhoneSlotToCurrentSite($entry, $site);
    }

    /**
     * Скопіювати телефонний слот на поточний сайт як site-перекриття: новий
     * DataValue (той самий ключ → природно перекриває успадкований), новий
     * PhoneSlot із тими ж налаштуваннями та копії всіх NumberEntry, що ділять ті
     * самі глобальні PhoneNumber (e164 унікальний — копія посилається на наявні
     * номери). Закріплення дзеркалимо за phone_number_id. Повертає запис копії,
     * що відповідає клікнутому (зі завантаженими звʼязками).
     */
    private function copyPhoneSlotToCurrentSite(NumberEntry $entry, Site $site): ?NumberEntry
    {
        $sourceSlot = $entry->slot;
        $sourceValue = $sourceSlot->dataValue;
        $clickedPhoneNumberId = (int) $entry->phone_number_id;
        $local = null;

        DB::transaction(function () use ($sourceSlot, $sourceValue, $site, $clickedPhoneNumberId, &$local): void {
            $copyValue = DataValue::create([
                'key' => $sourceValue->key,
                'value_type_id' => $sourceValue->value_type_id,
                'scope_type' => 'site',
                'scope_id' => $site->id,
                'content' => $sourceValue->content,
                'status' => $sourceValue->status ?? 'active',
            ]);
            $copyValue->geoTags()->sync($sourceValue->geoTags->pluck('id')->all());

            $copySlot = PhoneSlot::create([
                'data_value_id' => $copyValue->id,
                'return_mode' => $sourceSlot->return_mode,
                'exhaustion_policy' => $sourceSlot->exhaustion_policy,
                'emergency_number' => $sourceSlot->emergency_number,
            ]);

            // phone_number_id закріпленого запису джерела — щоб віддзеркалити пін.
            $pinnedPhoneNumberId = null;
            if ($sourceSlot->pinned_number_entry_id) {
                $pinnedSource = $sourceSlot->entries->firstWhere('id', $sourceSlot->pinned_number_entry_id);
                $pinnedPhoneNumberId = $pinnedSource ? (int) $pinnedSource->phone_number_id : null;
            }

            $pinnedCopyEntryId = null;
            foreach ($sourceSlot->entries as $sourceEntry) {
                $copyEntry = NumberEntry::create([
                    'phone_slot_id' => $copySlot->id,
                    'phone_number_id' => $sourceEntry->phone_number_id,
                    'priority' => $sourceEntry->priority,
                ]);

                if ((int) $sourceEntry->phone_number_id === $clickedPhoneNumberId) {
                    $local = $copyEntry;
                }
                if ($pinnedPhoneNumberId !== null && (int) $sourceEntry->phone_number_id === $pinnedPhoneNumberId) {
                    $pinnedCopyEntryId = $copyEntry->id;
                }
            }

            if ($pinnedCopyEntryId !== null) {
                $copySlot->update(['pinned_number_entry_id' => $pinnedCopyEntryId]);
            }

            app(FailoverEngine::class)->recompute($copySlot->fresh(), 'user');
        });

        if ($local) {
            AuditLog::create([
                'actor_type' => 'user',
                'action' => 'phone.materialized',
                'subject_type' => 'DataValue',
                'subject_id' => $local->slot->data_value_id,
                'new' => ['scope_id' => $site->id, 'key' => $sourceValue->key],
            ]);

            $local = $local->fresh(['slot.dataValue', 'phoneNumber']);
        }

        return $local;
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

    private function bulkTargetSites()
    {
        if (! $this->site) {
            return collect();
        }

        $site = Site::find($this->site);
        if (! $site) {
            return collect();
        }

        return match ($this->bulkScope) {
            'all' => app(AccessControl::class)->accessibleSites(auth()->user()),
            'selected' => $this->selected
                ? Site::whereIn('id', $this->selected)->get()
                : collect(),
            'tree' => Site::whereIn('id', app(SiteHierarchy::class)->descendantIds($site))->get(),
            'group' => $site->site_group_id
                ? Site::where('site_group_id', $site->site_group_id)->get()
                : collect([$site]),
            default => collect([$site]),
        };
    }

    private function replaceRecursively(mixed $value, string $find, string $replace, int &$count): mixed
    {
        if (is_string($value)) {
            $value = str_replace($find, $replace, $value, $count);
            return $value;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $newKey = is_string($key) ? str_replace($find, $replace, $key, $keyCount) : $key;
                $result[$newKey] = $this->replaceRecursively($item, $find, $replace, $itemCount);
                $count += ($keyCount ?? 0) + $itemCount;
            }
            return $result;
        }

        return $value;
    }

    private function countBulkHits(DataValue $value, string $find): int
    {
        $hits = substr_count($value->key, $find);
        $hits += $this->countRecursiveHits($value->content ?? [], $find);

        return $hits;
    }

    private function countRecursiveHits(mixed $value, string $find): int
    {
        if (is_string($value)) {
            return substr_count($value, $find);
        }

        if (! is_array($value)) {
            return 0;
        }

        $total = 0;
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $total += substr_count($key, $find);
            }
            $total += $this->countRecursiveHits($item, $find);
        }

        return $total;
    }
}
