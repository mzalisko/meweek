<?php

namespace App\Livewire;

use App\Admin\AffectedSites;
use App\Admin\AccessControl;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use App\Models\ValueType;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Livewire\Attributes\On;
use Livewire\Component;

class MessengerPanel extends Component
{
    public bool $open = false;

    public ?int $dataValueId = null;

    public string $newValue = '';

    public string $emergencyValue = '';

    public array $geoTagIds = [];

    #[On('close-messenger-panel')]
    public function closePanel(): void
    {
        $this->close();
    }

    #[On('open-messenger-slot')]
    public function open(int $dataValueId): void
    {
        $value = DataValue::with(['type', 'geoTags'])->find($dataValueId);

        if (! $this->isMessenger($value)) {
            $this->open = false;

            return;
        }

        $primary = $this->messengerGroup($value)->first() ?? $value;

        $this->dispatch('close-slot-panel');
        $this->dispatch('close-editor-panel');
        $this->dataValueId = $primary->id;
        $this->newValue = '';
        $this->emergencyValue = (string) ($primary->content['emergency_value'] ?? '');
        $this->geoTagIds = $primary->geoTags->pluck('id')->all();
        $this->resetValidation();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->newValue = '';
        $this->resetValidation();
    }

    public function addReserve(): void
    {
        if (! $this->canChangeCurrentValue()) {
            return;
        }

        $this->resetErrorBag('newValue');

        $primary = $this->primaryValue();
        if (! $primary) {
            return;
        }

        $value = trim($this->newValue);
        if ($value === '') {
            $this->addError('newValue', 'Введіть значення резерву.');

            return;
        }

        $content = $primary->content ?? [];
        $groupKey = $this->messengerGroupKey($primary);
        $newContent = [
            'value' => $value,
            'network' => $content['network'] ?? 'messenger',
            'url' => $this->messengerUrlFromValue($value),
            'messenger_slot' => $groupKey,
            'enabled' => true,
            'exhaustion_policy' => $content['exhaustion_policy'] ?? 'hide',
            'return_mode' => $content['return_mode'] ?? 'auto',
            'emergency_value' => $content['emergency_value'] ?? null,
            'emergency_url' => $content['emergency_url'] ?? null,
        ];

        $type = ValueType::firstOrCreate(['code' => 'messenger'], ['name' => 'messenger']);
        $reserve = DataValue::create([
            'key' => $this->uniqueMessengerKey($primary),
            'value_type_id' => $type->id,
            'scope_type' => $primary->scope_type,
            'scope_id' => $primary->scope_id,
            'content' => $newContent,
            'status' => 'active',
        ]);
        $reserve->geoTags()->sync($primary->geoTags->pluck('id')->all());

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.reserve_added',
            'subject_type' => 'DataValue',
            'subject_id' => $reserve->id,
            'new' => $newContent,
        ]);

        $this->newValue = '';
        $this->publishDataValue($reserve);
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: 'Резерв месенджера додано → опубліковано');
    }

    public function setExhaustionPolicy(string $policy): void
    {
        if (! $this->canChangeCurrentValue()) {
            return;
        }

        if (! in_array($policy, ['hide', 'last', 'emergency'], true)) {
            return;
        }

        $primary = $this->primaryValue();
        if (! $primary) {
            return;
        }

        foreach ($this->messengerGroup($primary) as $item) {
            $content = $item->content ?? [];
            $old = $content['exhaustion_policy'] ?? 'hide';
            if ($old === $policy) {
                continue;
            }

            $content['exhaustion_policy'] = $policy;
            $item->update(['content' => $content]);
        }

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.exhaustion_policy_changed',
            'subject_type' => 'DataValue',
            'subject_id' => $primary->id,
            'new' => ['exhaustion_policy' => $policy],
        ]);

        $this->publishDataValue($primary->fresh());
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

        $primary = $this->primaryValue();
        if (! $primary) {
            return;
        }

        foreach ($this->messengerGroup($primary) as $item) {
            $content = $item->content ?? [];
            $content['return_mode'] = $mode;
            $item->update(['content' => $content]);
        }

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.return_mode_changed',
            'subject_type' => 'DataValue',
            'subject_id' => $primary->id,
            'new' => ['return_mode' => $mode],
        ]);

        $this->publishDataValue($primary->fresh());
        $this->dispatch('slot-updated');
    }

    public function updatedEmergencyValue(): void
    {
        if (! $this->canChangeCurrentValue()) {
            return;
        }

        $primary = $this->primaryValue();
        if (! $primary) {
            return;
        }

        $newValue = trim($this->emergencyValue);
        foreach ($this->messengerGroup($primary) as $item) {
            $content = $item->content ?? [];
            $content['emergency_value'] = $newValue !== '' ? $newValue : null;
            $content['emergency_url'] = $newValue !== '' ? $this->messengerUrlFromValue($newValue) : null;
            $item->update(['content' => $content]);
        }

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.emergency_changed',
            'subject_type' => 'DataValue',
            'subject_id' => $primary->id,
            'new' => ['emergency_value' => $newValue !== '' ? $newValue : null],
        ]);

        $this->publishDataValue($primary->fresh());
        $this->dispatch('slot-updated');
    }

    public function hideSlot(): void
    {
        $this->setSlotStatus('hidden');
    }

    public function showSlot(): void
    {
        $this->setSlotStatus('active');
    }

    public function updatedGeoTagIds(): void
    {
        if (! $this->canChangeCurrentValue()) {
            return;
        }

        $primary = $this->primaryValue();
        if (! $primary) {
            return;
        }

        $oldGeoIds = $primary->geoTags->pluck('id')->sort()->values()->all();
        $newGeoIds = collect($this->geoTagIds)->map(fn ($id) => (int) $id)->sort()->values()->all();
        if ($oldGeoIds === $newGeoIds) {
            return;
        }

        foreach ($this->messengerGroup($primary) as $item) {
            $item->geoTags()->sync($this->geoTagIds);
        }

        AuditLog::create([
            'actor_type' => 'user',
            'action' => 'messenger.geo_changed',
            'subject_type' => 'DataValue',
            'subject_id' => $primary->id,
            'old' => ['geo_tag_ids' => $oldGeoIds],
            'new' => ['geo_tag_ids' => $newGeoIds],
        ]);

        $this->publishDataValue($primary->fresh());
        $this->dispatch('slot-updated');
    }

    public function render()
    {
        $value = $this->primaryValue();
        $group = $value ? $this->messengerGroup($value) : collect();
        $policy = $value ? ($value->content['exhaustion_policy'] ?? 'hide') : 'hide';
        $returnMode = $value ? ($value->content['return_mode'] ?? 'auto') : 'auto';
        $allHidden = $group->isNotEmpty()
            && $group->every(fn (DataValue $item) => ($item->status ?? 'active') === 'hidden');

        return view('livewire.messenger-panel', [
            'value' => $value,
            'group' => $group,
            'policy' => $policy,
            'returnMode' => $returnMode,
            'allHidden' => $allHidden,
            'allGeoTags' => GeoTag::orderBy('code')->get(),
        ]);
    }

    private function setSlotStatus(string $status): void
    {
        if (! $this->canChangeCurrentValue()) {
            return;
        }

        if (! in_array($status, ['active', 'hidden'], true)) {
            return;
        }

        $primary = $this->primaryValue();
        if (! $primary) {
            return;
        }

        $affectedSites = app(AffectedSites::class)->for($primary);
        foreach ($this->messengerGroup($primary) as $item) {
            if (($item->status ?? 'active') === $status) {
                continue;
            }

            $old = $item->status;
            $item->update(['status' => $status]);

            AuditLog::create([
                'actor_type' => 'user',
                'action' => $status === 'hidden' ? 'messenger.slot_hidden' : 'messenger.slot_shown',
                'subject_type' => 'DataValue',
                'subject_id' => $item->id,
                'old' => ['status' => $old],
                'new' => ['status' => $status],
            ]);
        }

        $this->publishSites($affectedSites);
        $this->dispatch('slot-updated');
        $this->dispatch('toast', message: $status === 'hidden' ? 'Слот месенджера приховано' : 'Слот месенджера показано');
    }

    private function primaryValue(): ?DataValue
    {
        if (! $this->dataValueId) {
            return null;
        }

        $value = DataValue::with(['type', 'geoTags'])->find($this->dataValueId);

        return $this->isMessenger($value) ? $value : null;
    }

    private function isMessenger(?DataValue $value): bool
    {
        return $value && $value->type && $value->type->code === 'messenger';
    }

    private function canChangeCurrentValue(): bool
    {
        $value = $this->primaryValue();
        if (! $value) {
            return false;
        }

        $access = app(AccessControl::class);

        return $access->canEditValue(auth()->user(), $value)
            && $access->canPublishValue(auth()->user(), $value);
    }

    private function messengerGroupKey(DataValue $value): string
    {
        return $value->content['messenger_slot'] ?? $value->key;
    }

    private function messengerGroup(DataValue $value)
    {
        $typeId = ValueType::where('code', 'messenger')->value('id');
        $groupKey = $this->messengerGroupKey($value);

        return DataValue::with(['type', 'geoTags'])
            ->where('value_type_id', $typeId)
            ->where('scope_type', $value->scope_type)
            ->where('scope_id', $value->scope_id)
            ->whereIn('status', ['active', 'hidden'])
            ->get()
            ->filter(fn (DataValue $item) => $this->messengerGroupKey($item) === $groupKey)
            ->sortBy(fn (DataValue $item) => sprintf(
                '%010d_%010d',
                $item->created_at?->getTimestamp() ?? 0,
                $item->id
            ))
            ->values();
    }

    private function messengerUrlFromValue(string $value): ?string
    {
        return preg_match('/^https?:\/\//i', $value) ? $value : null;
    }

    private function uniqueMessengerKey(DataValue $primary): string
    {
        $network = $primary->content['network'] ?? 'messenger';
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
