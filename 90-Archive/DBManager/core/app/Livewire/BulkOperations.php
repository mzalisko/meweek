<?php

namespace App\Livewire;

use App\Admin\AccessControl;
use App\Admin\PhoneNumberAssignment;
use App\Admin\SiteHierarchy;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\NumberEntry;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class BulkOperations extends Component
{
    public string $scope = 'all';

    public ?int $groupId = null;

    public ?int $rootSiteId = null;

    public string $siteSearch = '';

    public array $selectedSiteIds = [];

    public string $targetType = 'all';

    public string $search = '';

    public string $geoFilter = '';

    public string $stateFilter = '';

    public string $phoneFilter = '';

    public string $operation = 'replace_text';

    public string $findText = '';

    public string $replaceText = '';

    public string $contentValue = '';

    public string $statusValue = 'active';

    public string $geoMode = 'replace';

    public array $geoCodes = [];

    public string $phoneReplacement = '';

    public string $phoneStatus = 'active';

    public string $phoneFormat = '';

    public bool $publishAfterApply = true;

    public ?array $report = null;

    public function updatedScope(): void
    {
        $this->report = null;
    }

    public function updatedGroupId(): void
    {
        $this->report = null;
    }

    public function updatedRootSiteId(): void
    {
        $this->report = null;
    }

    public function updatedTargetType(): void
    {
        $this->report = null;
        if ($this->targetType === 'phone_reserve') {
            if (! in_array($this->operation, ['replace_phone', 'set_phone_status'], true)) {
                $this->operation = 'replace_phone';
            }
        }
        if ($this->targetType === 'address' && ! in_array($this->operation, ['set_geo', 'set_status'], true)) {
            $this->operation = 'set_geo';
        }
    }

    public function updatedOperation(): void
    {
        $this->report = null;
    }

    public function toggleSite(int $siteId): void
    {
        if (in_array($siteId, $this->selectedSiteIds, true)) {
            $this->selectedSiteIds = array_values(array_filter(
                $this->selectedSiteIds,
                fn ($id) => (int) $id !== $siteId
            ));
        } else {
            $this->selectedSiteIds[] = $siteId;
            $this->selectedSiteIds = array_values(array_unique(array_map('intval', $this->selectedSiteIds)));
        }

        $this->scope = 'selected';
        $this->report = null;
    }

    public function selectFilteredSites(): void
    {
        $this->selectedSiteIds = $this->sitePool(false)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $this->scope = 'selected';
        $this->report = null;
    }

    public function clearSiteSelection(): void
    {
        $this->selectedSiteIds = [];
        if ($this->scope === 'selected') {
            $this->scope = 'all';
        }
        $this->report = null;
    }

    public function resetFilters(): void
    {
        $this->targetType = 'all';
        $this->stateFilter = '';
        $this->geoFilter = '';
        $this->search = '';
        $this->phoneFilter = '';
        $this->report = null;
    }

    public function apply(): void
    {
        $this->resetValidation();
        $this->report = null;

        if ($this->targetType === 'phone_reserve') {
            if (! in_array($this->operation, ['replace_phone', 'set_phone_status'], true)) {
                $this->addError('operation', 'Для резервних телефонів доступна лише заміна номера або зміна стану.');

                return;
            }
        }

        // Адреса — структурований тип: текстові масові операції псували б окремі поля.
        if ($this->targetType === 'address' && in_array($this->operation, ['replace_text', 'set_value'], true)) {
            $this->addError('operation', 'Для адрес доступні лише зміна стану та гео — структуровані поля редагуються в картці значення.');

            return;
        }

        if ($this->isPhoneOperation()) {
            $this->applyPhoneOperation();

            return;
        }

        $this->applyValueOperation();
    }

    public function render()
    {
        $accessibleSites = app(AccessControl::class)->accessibleSites(auth()->user());
        $siteOptions = $this->sitePool(false);
        $editableSiteIds = $this->editableTargetSites()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $previewRows = $this->previewRows();
        $stats = $this->stats($previewRows, $editableSiteIds);

        $groups = SiteGroup::whereIn('id', $accessibleSites->pluck('site_group_id')->filter()->unique())
            ->orderBy('name')
            ->get();

        $geoTags = GeoTag::orderByRaw("CASE WHEN code LIKE '!%' THEN 1 ELSE 0 END")
            ->orderBy('code')
            ->get();

        return view('livewire.bulk-operations', [
            'sites' => $accessibleSites,
            'siteOptions' => $siteOptions,
            'groups' => $groups,
            'geoTags' => $geoTags,
            'previewRows' => $previewRows,
            'stats' => $stats,
            'editableSiteIds' => $editableSiteIds,
        ])->layout('components.layouts.admin');
    }

    private function applyValueOperation(): void
    {
        $values = $this->matchedDataValues();

        if ($values->isEmpty()) {
            $this->addError('operation', 'Немає записів для зміни.');

            return;
        }

        if ($this->operation === 'replace_text' && trim($this->findText) === '') {
            $this->addError('findText', 'Вкажіть, що саме замінити.');

            return;
        }

        if ($this->operation === 'set_phone_format') {
            $format = trim($this->phoneFormat);
            if ($format !== '' && ! \App\Support\PhoneFormatter::isValidPattern($format)) {
                $this->addError('phoneFormat', 'Використовуйте # для цифр і роздільники: пробіл, +, -, (), крапка.');

                return;
            }
        }

        $batchId = (string) Str::uuid();
        $changedValueIds = [];
        $changedSiteIds = [];
        $geoIds = GeoTag::whereIn('code', $this->geoCodes)->pluck('id', 'code');

        DB::transaction(function () use ($values, $batchId, &$changedValueIds, &$changedSiteIds, $geoIds): void {
            foreach ($values as $value) {
                $old = [
                    'key' => $value->key,
                    'content' => $value->content,
                    'status' => $value->status,
                    'geo' => $this->valueGeoCodes($value),
                ];

                $changed = false;

                if ($this->operation === 'replace_text') {
                    $content = $value->content ?? [];
                    $contentChanges = 0;
                    $newKey = str_replace($this->findText, $this->replaceText, $value->key, $keyChanges);
                    $newContent = $this->replaceRecursively($content, $this->findText, $this->replaceText, $contentChanges);

                    if ($keyChanges > 0 || $contentChanges > 0) {
                        $value->key = $newKey;
                        $value->content = $newContent;
                        $changed = true;
                    }
                } elseif ($this->operation === 'set_value') {
                    $content = $value->content ?? [];
                    $content['value'] = $this->contentValue;
                    $value->content = $content;
                    $changed = true;
                } elseif ($this->operation === 'set_status') {
                    if ($value->status !== $this->statusValue) {
                        $value->status = $this->statusValue;
                        $changed = true;
                    }
                } elseif ($this->operation === 'set_phone_format') {
                    if (($value->type?->code ?? null) === 'phone') {
                        $content = $value->content ?? [];
                        $oldFormat = $content['phone_format'] ?? '';
                        $newFormat = trim($this->phoneFormat);

                        if ($newFormat === '') {
                            if (isset($content['phone_format'])) {
                                unset($content['phone_format']);
                                $value->content = $content;
                                $changed = true;
                            }
                        } else {
                            if ($oldFormat !== $newFormat) {
                                $content['phone_format'] = $newFormat;
                                $value->content = $content;
                                $changed = true;
                            }
                        }
                    }
                }

                if ($changed) {
                    $value->save();
                }

                if ($this->operation === 'set_geo') {
                    $current = $value->geoTags->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $selected = $geoIds->values()->map(fn ($id) => (int) $id)->all();
                    $next = match ($this->geoMode) {
                        'add' => array_values(array_unique(array_merge($current, $selected))),
                        'remove' => array_values(array_diff($current, $selected)),
                        default => $selected,
                    };

                    if ($this->sameIds($current, $next) === false) {
                        $value->geoTags()->sync($next);
                        $changed = true;
                    }

                    if (($value->type?->code ?? null) === 'price') {
                        $content = $value->content ?? [];
                        $prices = collect($content['prices'] ?? [])
                            ->map(function (array $price): array {
                                $currentCodes = $price['geo'] ?? ['WORLD'];
                                $currentCodes = $currentCodes === [] ? ['WORLD'] : $currentCodes;
                                $selectedCodes = $this->geoCodes === [] ? ['WORLD'] : $this->geoCodes;

                                $nextCodes = match ($this->geoMode) {
                                    'add' => array_values(array_unique(array_merge($currentCodes, $selectedCodes))),
                                    'remove' => array_values(array_diff($currentCodes, $selectedCodes)),
                                    default => $selectedCodes,
                                };

                                $price['geo'] = $nextCodes === [] ? ['WORLD'] : array_values($nextCodes);

                                return $price;
                            })
                            ->all();

                        if (($content['prices'] ?? []) !== $prices) {
                            $content['prices'] = $prices;
                            $value->content = $content;
                            $value->save();
                            $changed = true;
                        }
                    }
                }

                if (! $changed) {
                    continue;
                }

                $changedValueIds[] = (int) $value->id;
                $changedSiteIds[] = (int) $value->scope_id;

                AuditLog::create([
                    'action' => 'bulk.' . $this->operation,
                    'subject_type' => 'DataValue',
                    'subject_id' => $value->id,
                    'old' => $old,
                    'new' => [
                        'key' => $value->fresh()->key,
                        'content' => $value->fresh()->content,
                        'status' => $value->fresh()->status,
                        'geo' => $this->valueGeoCodes($value->fresh(['type', 'geoTags'])),
                    ],
                    'batch_id' => $batchId,
                ]);
            }
        });

        $this->publishChangedSites($changedSiteIds);

        $this->report = [
            'changed' => count(array_unique($changedValueIds)),
            'sites' => count(array_unique($changedSiteIds)),
            'batch' => $batchId,
        ];
    }

    private function applyPhoneOperation(): void
    {
        $entries = $this->matchedNumberEntries();

        if ($entries->isEmpty()) {
            $this->addError('operation', 'Немає номерів для зміни.');

            return;
        }

        $replacement = null;
        if ($this->operation === 'replace_phone') {
            if (trim($this->phoneFilter) === '') {
                $this->addError('phoneFilter', 'Спершу вкажіть номер або фрагмент номера для пошуку.');

                return;
            }

            $replacement = $this->normalizePhone($this->phoneReplacement);
            if ($replacement === null) {
                $this->addError('phoneReplacement', 'Введіть номер у форматі +380...');

                return;
            }
        }

        $batchId = (string) Str::uuid();
        $changedEntries = [];
        $changedSiteIds = [];

        DB::transaction(function () use ($entries, $replacement, $batchId, &$changedEntries, &$changedSiteIds): void {
            foreach ($entries as $entry) {
                $entry->loadMissing(['slot.dataValue', 'phoneNumber']);
                $value = $entry->slot?->dataValue;

                if (! $value) {
                    continue;
                }

                $old = [
                    'entry_id' => $entry->id,
                    'phone' => $entry->phoneNumber?->e164,
                    'phone_status' => $entry->phoneNumber?->status,
                    'data_value_id' => $value->id,
                ];

                if ($this->operation === 'replace_phone') {
                    if ($entry->phoneNumber?->e164 === $replacement) {
                        continue;
                    }

                    $entry = app(PhoneNumberAssignment::class)->assign($entry, $replacement);
                } elseif ($this->operation === 'set_phone_status') {
                    $phone = $entry->phoneNumber;
                    if (! $phone || $phone->status === $this->phoneStatus) {
                        continue;
                    }

                    $phone->update([
                        'status' => $this->phoneStatus,
                        'down_since' => $this->phoneStatus === 'down' ? now() : null,
                    ]);
                    $entry = $entry->fresh(['slot.dataValue', 'phoneNumber']);
                }

                $changedEntries[] = (int) $entry->id;
                $changedSiteIds[] = (int) $value->scope_id;

                AuditLog::create([
                    'action' => 'bulk.' . $this->operation,
                    'subject_type' => 'DataValue',
                    'subject_id' => $value->id,
                    'old' => $old,
                    'new' => [
                        'entry_id' => $entry->id,
                        'phone' => $entry->phoneNumber?->e164,
                        'phone_status' => $entry->phoneNumber?->status,
                        'data_value_id' => $value->id,
                    ],
                    'batch_id' => $batchId,
                ]);
            }
        });

        $this->publishChangedSites($changedSiteIds);

        $this->report = [
            'changed' => count(array_unique($changedEntries)),
            'sites' => count(array_unique($changedSiteIds)),
            'batch' => $batchId,
        ];
    }

    private function matchedDataValues(): Collection
    {
        $siteIds = $this->editableTargetSites()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($siteIds === []) {
            return collect();
        }

        return DataValue::with(['type', 'geoTags', 'phoneSlot.entries.phoneNumber'])
            ->where('scope_type', 'site')
            ->whereIn('scope_id', $siteIds)
            ->when($this->targetType !== 'all', function (Builder $query) {
                $typeCode = $this->targetType === 'phone_reserve' ? 'phone' : $this->targetType;
                $query->whereHas('type', fn (Builder $typeQuery) => $typeQuery->where('code', $typeCode));
            })
            ->when(in_array($this->stateFilter, ['active', 'hidden'], true), fn (Builder $query) => $query->where('status', $this->stateFilter))
            ->get()
            ->filter(fn (DataValue $value) => $this->matchesValueFilters($value))
            ->values();
    }

    private function matchedNumberEntries(): Collection
    {
        $siteIds = $this->editableTargetSites()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($siteIds === []) {
            return collect();
        }

        return NumberEntry::with(['phoneNumber', 'slot.dataValue.type', 'slot.dataValue.geoTags'])
            ->whereHas('slot.dataValue', function (Builder $query) use ($siteIds): void {
                $query->where('scope_type', 'site')
                    ->whereIn('scope_id', $siteIds)
                    ->whereHas('type', fn (Builder $typeQuery) => $typeQuery->where('code', 'phone'));
            })
            ->when($this->targetType === 'phone_reserve', function ($query) {
                $query->where('priority', '>', 0);
            })
            ->when($this->targetType !== 'phone_reserve', function ($query) {
                $query->where('priority', 0);
            })
            ->get()
            ->filter(function (NumberEntry $entry): bool {
                $value = $entry->slot?->dataValue;

                return $value
                    && $this->matchesValueFilters($value)
                    && $this->matchesPhoneFilter($entry);
            })
            ->values();
    }

    private function matchesValueFilters(DataValue $value): bool
    {
        if ($this->geoFilter !== '') {
            $codes = $this->valueGeoCodes($value);
            $isWorld = $codes === [];

            if ($this->geoFilter === 'WORLD') {
                if (! $isWorld) {
                    return false;
                }
            } elseif (! in_array($this->geoFilter, $codes, true)) {
                return false;
            }
        }

        if ($this->stateFilter === 'down') {
            $hasDown = $value->phoneSlot?->entries
                ->contains(fn (NumberEntry $entry) => $entry->phoneNumber?->status === 'down') ?? false;

            if (! $hasDown) {
                return false;
            }
        }

        if (trim($this->search) !== '') {
            $haystack = mb_strtolower($value->key . ' ' . json_encode($value->content ?? [], JSON_UNESCAPED_UNICODE));
            if (! str_contains($haystack, mb_strtolower(trim($this->search)))) {
                return false;
            }
        }

        if (trim($this->phoneFilter) !== '') {
            $needle = $this->phoneNeedle();
            $hasPhone = $value->phoneSlot?->entries
                ->contains(fn (NumberEntry $entry) => str_contains($entry->phoneNumber?->e164 ?? '', $needle)) ?? false;

            if (! $hasPhone) {
                return false;
            }
        }

        return true;
    }

    private function matchesPhoneFilter(NumberEntry $entry): bool
    {
        if (trim($this->phoneFilter) === '') {
            return true;
        }

        return str_contains($entry->phoneNumber?->e164 ?? '', $this->phoneNeedle());
    }

    private function previewRows(): array
    {
        $sites = $this->editableTargetSites()->keyBy('id');
        $geoMap = GeoTag::pluck('code', 'id')->all();
        $geoCodeToIdMap = array_flip($geoMap);

        $translateState = fn (?string $s) => match ($s) {
            'active' => 'Активний',
            'hidden' => 'Прихований',
            'down' => 'Збій',
            default => $s ?? 'Невідомо',
        };

        if ($this->isPhoneOperation()) {
            return $this->matchedNumberEntries()
                ->take(80)
                ->map(function (NumberEntry $entry) use ($sites, $translateState): array {
                    $value = $entry->slot?->dataValue;
                    $site = $value ? $sites->get((int) $value->scope_id) : null;

                    $calc = $this->calculateNewPhone($entry);
                    $format = $value ? ($value->content['phone_format'] ?? null) : null;

                    return [
                        'kind' => 'phone',
                        'site' => $site?->domain ?? 'site #' . ($value?->scope_id ?? '?'),
                        'group' => $site?->group?->name ?? 'Без групи',
                        'key' => $value?->key ?? 'phone',
                        'type' => $entry->priority === 0 ? 'phone' : 'phone_reserve',
                        'geo' => $value ? ($this->valueGeoCodes($value) ?: ['WORLD']) : ['WORLD'],
                        'state' => $translateState($entry->phoneNumber?->status ?? 'unknown'),
                        'value' => $entry->phoneNumber?->e164 ?? '—',
                        'format' => $format,
                        'changed' => $calc['changed'],
                        'new_value' => $calc['new_value'],
                        'new_state' => $translateState($calc['new_state']),
                        'new_geo' => $value ? ($this->valueGeoCodes($value) ?: ['WORLD']) : ['WORLD'],
                        'new_format' => $format,
                    ];
                })
                ->values()
                ->all();
        }

        return $this->matchedDataValues()
            ->take(80)
            ->map(function (DataValue $value) use ($sites, $geoMap, $geoCodeToIdMap, $translateState): array {
                $site = $sites->get((int) $value->scope_id);
                $type = $value->type?->code ?? 'unknown';

                $calc = $this->calculateNewValue($value, $geoMap, $geoCodeToIdMap);
                $format = $type === 'phone' ? ($value->content['phone_format'] ?? null) : null;
                $newFormat = $format;
                if ($type === 'phone' && $this->operation === 'set_phone_format') {
                    $newFormat = trim($this->phoneFormat);
                }

                return [
                    'kind' => 'value',
                    'site' => $site?->domain ?? 'site #' . $value->scope_id,
                    'group' => $site?->group?->name ?? 'Без групи',
                    'key' => $value->key,
                    'type' => $type,
                    'geo' => $this->valueGeoCodes($value) ?: ['WORLD'],
                    'state' => $translateState($value->status),
                    'value' => $this->displayValue($value),
                    'format' => $format,
                    'changed' => $calc['changed'],
                    'new_value' => $calc['new_value'],
                    'new_state' => $translateState($calc['new_state']),
                    'new_geo' => $calc['new_geo'],
                    'new_key' => $calc['new_key'],
                    'new_format' => $newFormat,
                ];
            })
            ->values()
            ->all();
    }

    private function calculateNewValue(DataValue $value, array $geoMap, array $geoCodeToIdMap): array
    {
        $cloned = clone $value;
        $changed = false;
        $newKey = $value->key;
        $newStatus = $value->status;

        $currentGeo = $this->valueGeoCodes($value);
        $newGeo = $currentGeo;

        if ($this->operation === 'replace_text' && trim($this->findText) !== '') {
            $content = $cloned->content ?? [];
            $contentChanges = 0;
            $newKey = str_replace($this->findText, $this->replaceText, $cloned->key, $keyChanges);
            $newContent = $this->replaceRecursively($content, $this->findText, $this->replaceText, $contentChanges);

            if ($keyChanges > 0 || $contentChanges > 0) {
                $cloned->key = $newKey;
                $cloned->content = $newContent;
                $changed = true;
            }
        } elseif ($this->operation === 'set_value') {
            $content = $cloned->content ?? [];
            $content['value'] = $this->contentValue;
            $cloned->content = $content;
            $changed = true;
        } elseif ($this->operation === 'set_status') {
            if ($cloned->status !== $this->statusValue) {
                $cloned->status = $this->statusValue;
                $newStatus = $this->statusValue;
                $changed = true;
            }
        } elseif ($this->operation === 'set_phone_format') {
            if (($cloned->type?->code ?? null) === 'phone') {
                $content = $cloned->content ?? [];
                $newFormat = trim($this->phoneFormat);
                if ($newFormat === '') {
                    unset($content['phone_format']);
                } else {
                    $content['phone_format'] = $newFormat;
                }
                $cloned->content = $content;
                $changed = true;
            }
        } elseif ($this->operation === 'set_geo') {
            $currentGeoIds = $value->geoTags->pluck('id')->map(fn ($id) => (int) $id)->all();

            $selectedGeoIds = [];
            foreach ($this->geoCodes as $code) {
                if (isset($geoCodeToIdMap[$code])) {
                    $selectedGeoIds[] = (int) $geoCodeToIdMap[$code];
                }
            }

            $nextGeoIds = match ($this->geoMode) {
                'add' => array_values(array_unique(array_merge($currentGeoIds, $selectedGeoIds))),
                'remove' => array_values(array_diff($currentGeoIds, $selectedGeoIds)),
                default => $selectedGeoIds,
            };

            $newGeo = [];
            foreach ($nextGeoIds as $id) {
                if (isset($geoMap[$id])) {
                    $newGeo[] = $geoMap[$id];
                }
            }

            if (($cloned->type?->code ?? null) === 'price') {
                $content = $cloned->content ?? [];
                $prices = collect($content['prices'] ?? [])
                    ->map(function (array $price): array {
                        $currentCodes = $price['geo'] ?? ['WORLD'];
                        $currentCodes = $currentCodes === [] ? ['WORLD'] : $currentCodes;
                        $selectedCodes = $this->geoCodes === [] ? ['WORLD'] : $this->geoCodes;

                        $nextCodes = match ($this->geoMode) {
                            'add' => array_values(array_unique(array_merge($currentCodes, $selectedCodes))),
                            'remove' => array_values(array_diff($currentCodes, $selectedCodes)),
                            default => $selectedCodes,
                        };

                        $price['geo'] = $nextCodes === [] ? ['WORLD'] : array_values($nextCodes);

                        return $price;
                    })
                    ->all();

                if (($content['prices'] ?? []) !== $prices) {
                    $content['prices'] = $prices;
                    $cloned->content = $content;
                    $changed = true;
                }
            } else {
                if ($this->sameIds($currentGeoIds, $nextGeoIds) === false) {
                    $changed = true;
                }
            }
        }

        return [
            'changed' => $changed || ($newKey !== $value->key),
            'new_key' => $newKey,
            'new_value' => $this->displayValue($cloned),
            'new_state' => $newStatus,
            'new_geo' => $newGeo ?: ['WORLD'],
        ];
    }

    private function calculateNewPhone(NumberEntry $entry): array
    {
        $changed = false;
        $newPhone = $entry->phoneNumber?->e164 ?? '—';
        $newStatus = $entry->phoneNumber?->status ?? 'unknown';

        if ($this->operation === 'replace_phone' && trim($this->phoneReplacement) !== '') {
            $normalized = $this->normalizePhone($this->phoneReplacement);
            if ($normalized !== null && $newPhone !== $normalized) {
                $newPhone = $normalized;
                $changed = true;
            }
        } elseif ($this->operation === 'set_phone_status') {
            if ($newStatus !== $this->phoneStatus) {
                $newStatus = $this->phoneStatus;
                $changed = true;
            }
        }

        return [
            'changed' => $changed,
            'new_value' => $newPhone,
            'new_state' => $newStatus,
        ];
    }

    private function stats(array $previewRows, array $editableSiteIds): array
    {
        $matchedCount = $this->isPhoneOperation()
            ? $this->matchedNumberEntries()->count()
            : $this->matchedDataValues()->count();

        return [
            'sites' => count($editableSiteIds),
            'matched' => $matchedCount,
            'preview' => count($previewRows),
            'phones' => $this->matchedNumberEntries()->count(),
            'limited' => $matchedCount > count($previewRows),
        ];
    }

    private function editableTargetSites(): Collection
    {
        return $this->sitePool()
            ->filter(fn (Site $site) => app(AccessControl::class)->canEditSite(auth()->user(), $site)
                && app(AccessControl::class)->canPublishSite(auth()->user(), $site))
            ->values();
    }

    private function sitePool(bool $applySelectedScope = true): Collection
    {
        $sites = app(AccessControl::class)->accessibleSites(auth()->user());

        if (trim($this->siteSearch) !== '') {
            $needle = mb_strtolower(trim($this->siteSearch));
            $sites = $sites->filter(fn (Site $site) => str_contains(mb_strtolower($site->domain . ' ' . $site->name), $needle));
        }

        $sites = match ($this->scope) {
            'group' => $this->groupId
                ? $sites->where('site_group_id', $this->groupId)
                : collect(),
            'tree' => $this->rootSiteId
                ? $sites->whereIn('id', app(SiteHierarchy::class)->descendantIds($this->rootSiteId))
                : collect(),
            'selected' => $applySelectedScope
                ? $sites->whereIn('id', array_map('intval', $this->selectedSiteIds))
                : $sites,
            default => $sites,
        };

        return $sites->sortBy('domain')->values();
    }

    private function isPhoneOperation(): bool
    {
        return in_array($this->operation, ['replace_phone', 'set_phone_status'], true);
    }

    private function replaceRecursively(mixed $value, string $find, string $replace, int &$count): mixed
    {
        $count = $count ?? 0;

        if (is_string($value)) {
            return str_replace($find, $replace, $value, $count);
        }

        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $keyCount = 0;
            $itemCount = 0;
            $newKey = is_string($key) ? str_replace($find, $replace, $key, $keyCount) : $key;
            $result[$newKey] = $this->replaceRecursively($item, $find, $replace, $itemCount);
            $count += $keyCount + $itemCount;
        }

        return $result;
    }

    private function sameIds(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left === $right;
    }

    private function displayValue(DataValue $value): string
    {
        $content = $value->content ?? [];

        if (($value->type?->code ?? null) === 'phone') {
            return $value->phoneSlot?->entries
                ->filter(fn (NumberEntry $entry) => $entry->priority === 0)
                ->map(fn (NumberEntry $entry) => $entry->phoneNumber?->e164)
                ->filter()
                ->implode(', ') ?: '—';
        }

        if (($value->type?->code ?? null) === 'price') {
            return collect($content['prices'] ?? [])
                ->map(fn ($price) => ($price['label'] ?? 'Ціна') . ': ' . ($price['value'] ?? '—'))
                ->implode('; ') ?: '—';
        }

        return (string) ($content['value'] ?? $content['url'] ?? '—');
    }

    private function valueGeoCodes(DataValue $value): array
    {
        if (($value->type?->code ?? null) === 'price') {
            return collect($value->content['prices'] ?? [])
                ->flatMap(fn (array $price) => $price['geo'] ?? ['WORLD'])
                ->filter(fn ($code) => is_string($code) && $code !== '')
                ->unique()
                ->values()
                ->all();
        }

        return $value->geoTags->pluck('code')->all();
    }

    private function phoneNeedle(): string
    {
        return preg_replace('/[^\d+]+/', '', trim($this->phoneFilter)) ?? '';
    }

    private function normalizePhone(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/(?!^\+)[^\d]/', '', $value) ?? '';
        if (! str_starts_with($normalized, '+')) {
            $normalized = '+' . preg_replace('/\D+/', '', $normalized);
        }

        return preg_match('/^\+\d{7,20}$/', $normalized) === 1 ? $normalized : null;
    }

    private function publishChangedSites(array $siteIds): void
    {
        if (! $this->publishAfterApply) {
            return;
        }

        Site::whereIn('id', array_unique(array_map('intval', $siteIds)))
            ->get()
            ->each(function (Site $site): void {
                $publication = app(SitePayloadCompiler::class)->publish($site);
                app(BridgePublisher::class)->push($publication);
            });
    }

    public function getRecentBulkSessions(): Collection
    {
        $rolledBackLogIds = AuditLog::where('action', 'audit.restored')
            ->where('subject_type', 'AuditLog')
            ->pluck('subject_id')
            ->all();

        $rolledBackBatchIds = AuditLog::whereIn('id', $rolledBackLogIds)
            ->whereNotNull('batch_id')
            ->pluck('batch_id')
            ->unique()
            ->all();

        return AuditLog::where('action', 'like', 'bulk.%')
            ->whereNotNull('batch_id')
            ->when(!empty($rolledBackBatchIds), function ($query) use ($rolledBackBatchIds) {
                $query->whereNotIn('batch_id', $rolledBackBatchIds);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('batch_id')
            ->take(3)
            ->map(function ($logs, $batchId) {
                $first = $logs->first();
                
                $details = $logs->map(function ($log) {
                    $siteId = $log->old['scope_id'] ?? $log->new['scope_id'] ?? null;
                    $scopeType = $log->old['scope_type'] ?? $log->new['scope_type'] ?? null;
                    $siteDomain = 'Сайт #' . $siteId;
                    if ($scopeType === 'site' && $siteId) {
                        $siteDomain = \App\Models\Site::where('id', $siteId)->value('domain') ?? $siteDomain;
                    }
                    
                    $key = $log->old['key'] ?? $log->new['key'] ?? null;
                    if (!$key && $log->subject_type === 'DataValue' && $log->subject_id) {
                        $key = \App\Models\DataValue::where('id', $log->subject_id)->value('key');
                    }
                    
                    $changeDesc = '';
                    if ($log->action === 'bulk.replace_phone') {
                        $changeDesc = ($log->old['phone'] ?? '—') . ' ➔ ' . ($log->new['phone'] ?? '—');
                    } elseif ($log->action === 'bulk.set_phone_status') {
                        $oldStat = ($log->old['phone_status'] ?? '') === 'down' ? 'збій' : (($log->old['phone_status'] ?? '') === 'active' ? 'активний' : ($log->old['phone_status'] ?? '—'));
                        $newStat = ($log->new['phone_status'] ?? '') === 'down' ? 'збій' : (($log->new['phone_status'] ?? '') === 'active' ? 'активний' : ($log->new['phone_status'] ?? '—'));
                        $changeDesc = ($log->new['phone'] ?? '') . ' [' . $oldStat . ' ➔ ' . $newStat . ']';
                    } elseif ($log->action === 'bulk.set_phone_format') {
                        $changeDesc = 'формат: ' . ($log->old['content']['phone_format'] ?? '—') . ' ➔ ' . ($log->new['content']['phone_format'] ?? '—');
                    } elseif ($log->action === 'bulk.set_status') {
                        $oldStat = ($log->old['status'] ?? '') === 'hidden' ? 'прихований' : (($log->old['status'] ?? '') === 'active' ? 'активний' : ($log->old['status'] ?? '—'));
                        $newStat = ($log->new['status'] ?? '') === 'hidden' ? 'прихований' : (($log->new['status'] ?? '') === 'active' ? 'активний' : ($log->new['status'] ?? '—'));
                        $changeDesc = 'статус: ' . $oldStat . ' ➔ ' . $newStat;
                    } elseif ($log->action === 'bulk.set_geo') {
                        $oldGeo = implode(',', $log->old['geo'] ?? []);
                        $newGeo = implode(',', $log->new['geo'] ?? []);
                        $changeDesc = 'гео: ' . ($oldGeo ?: '—') . ' ➔ ' . ($newGeo ?: '—');
                    } else {
                        $oldVal = $log->old['content']['value'] ?? $log->old['key'] ?? '';
                        $newVal = $log->new['content']['value'] ?? $log->new['key'] ?? '';
                        if (is_array($oldVal)) {
                            $oldVal = json_encode($oldVal, JSON_UNESCAPED_UNICODE);
                        }
                        if (is_array($newVal)) {
                            $newVal = json_encode($newVal, JSON_UNESCAPED_UNICODE);
                        }
                        $changeDesc = \Illuminate\Support\Str::limit((string)$oldVal, 30) . ' ➔ ' . \Illuminate\Support\Str::limit((string)$newVal, 30);
                    }
                    
                    return [
                        'site' => $siteDomain,
                        'key' => $key,
                        'change' => $changeDesc,
                    ];
                })->values();

                return [
                    'batch_id' => $batchId,
                    'action' => $first->action,
                    'created_at' => $first->created_at,
                    'count' => $logs->count(),
                    'details' => $details,
                ];
            })
            ->values();
    }

    public function rollbackBatch(string $batchId): void
    {
        $logs = AuditLog::where('batch_id', $batchId)->get();
        if ($logs->isEmpty()) {
            $this->dispatch('toast', message: 'Сесію не знайдено або вже скасовано.');
            return;
        }

        $user = auth()->user();
        $firstLog = $logs->first();

        if (\App\Services\Audit\AuditRestorer::restore($firstLog, $user)) {
            $this->dispatch('toast', message: 'Масові зміни скасовано!');
            $this->report = null;
        } else {
            $this->dispatch('toast', message: 'Помилка при скасуванні змін.');
        }
    }
}
