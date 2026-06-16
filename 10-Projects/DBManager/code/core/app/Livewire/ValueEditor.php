<?php

namespace App\Livewire;

use App\Admin\AffectedSites;
use App\Admin\AccessControl;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\PhoneSlot;
use App\Models\Site;
use App\Models\ValueType;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Livewire\Attributes\On;
use Livewire\Component;

class ValueEditor extends Component
{
    public bool $open = false;

    #[On('close-editor-panel')]
    public function closePanel(): void
    {
        $this->open = false;
    }

    public ?int $siteId = null;

    public ?int $valueId = null;

    public string $type = 'phone';

    public string $key = '';

    public string $value = '';

    public string $scope = 'site';

    public ?string $network = null;

    public ?string $url = null;

    public array $geoTagIds = [];

    #[On('open-value-editor')]
    public function openCreate(int $siteId): void
    {
        $this->createFor($siteId);
    }

    public function createFor(int $siteId): void
    {
        if (! $this->canChangeSite($siteId)) {
            return;
        }

        $this->siteId = $siteId;
        $this->valueId = null;
        $this->type = 'phone';
        $this->key = '';
        $this->value = '';
        $this->scope = 'site';
        $this->network = null;
        $this->url = null;
        $this->geoTagIds = [];
        $this->resetValidation();
        $this->dispatch('close-messenger-panel');
        $this->dispatch('close-slot-panel');
        $this->open = true;
    }

    #[On('edit-value')]
    public function edit(int $valueId): void
    {
        $dv = DataValue::findOrFail($valueId);
        if (! $this->canChangeValue($dv)) {
            return;
        }

        $this->valueId = $dv->id;
        $this->siteId  = $dv->scope_type === 'site' ? $dv->scope_id : null;
        $this->type    = $dv->type->code;
        $this->key     = $dv->key;
        $this->value   = $dv->content['value'] ?? ($dv->content['name'] ?? '');
        $this->scope   = $dv->scope_type;
        $this->network   = $dv->content['network'] ?? null;
        $this->url       = $dv->content['url'] ?? null;
        $this->geoTagIds = $dv->geoTags->pluck('id')->toArray();
        $this->resetValidation();
        $this->dispatch('close-messenger-panel');
        $this->dispatch('close-slot-panel');
        $this->open    = true;
    }

    public function save(): void
    {
        if (! $this->canSaveCurrentTarget()) {
            return;
        }

        $rules = [
            'key'   => ['required', 'regex:/^[a-z0-9_]+$/'],
            'type'  => ['required', $this->valueId ? 'in:text,price,messenger,address,social,phone' : 'in:phone,messenger'],
            'scope' => ['required', 'in:group,site'],
        ];

        if ($this->type !== 'phone') {
            $rules['value'] = ['required'];
        }
        if ($this->type === 'messenger') {
            $rules['network'] = ['nullable', 'string', 'max:64'];
        }

        $this->validate($rules);

        // Build content
        $content = $this->type === 'phone' ? [] : ['value' => $this->value !== '' ? $this->value : null];
        if ($this->type === 'messenger') {
            $content['network'] = $this->network;
            $content['url']     = $this->url ?: $this->messengerUrlFromValue($this->value);
            if (! $this->valueId) {
                $content['exhaustion_policy'] = 'hide';
            }
        }

        // Resolve value_type_id
        $valueType = ValueType::firstOrCreate(
            ['code' => $this->type],
            ['name' => $this->type],
        );

        if ($this->valueId) {
            // UPDATE existing value
            $dv         = DataValue::with('geoTags')->findOrFail($this->valueId);
            $oldContent = $dv->content;
            $oldGeoIds  = $dv->geoTags->pluck('id')->sort()->values()->all();

            if ($this->type === 'messenger') {
                $content = collect($oldContent ?? [])
                    ->only([
                        'messenger_slot',
                        'linked_slot',
                        'enabled',
                        'pinned',
                        'exhaustion_policy',
                        'return_mode',
                        'current_messenger_id',
                        'last_active_value',
                        'last_active_url',
                        'emergency_value',
                        'emergency_url',
                    ])
                    ->merge($content)
                    ->all();
                $content['exhaustion_policy'] ??= 'hide';
            }

            $dv->update([
                'key'           => $this->key,
                'value_type_id' => $valueType->id,
                'content'       => $content,
            ]);

            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => 'value.updated',
                'subject_type' => 'DataValue',
                'subject_id'   => $dv->id,
                'old'          => $oldContent,
                'new'          => $content,
            ]);

            $newGeoIds = collect($this->geoTagIds)->sort()->values()->all();
            if ($oldGeoIds !== $newGeoIds) {
                $dv->geoTags()->sync($this->geoTagIds);
                AuditLog::create([
                    'actor_type'   => 'user',
                    'action'       => 'value.geo_changed',
                    'subject_type' => 'DataValue',
                    'subject_id'   => $dv->id,
                    'old'          => ['geo_tag_ids' => $oldGeoIds],
                    'new'          => ['geo_tag_ids' => $newGeoIds],
                ]);
            }
        } else {
            // CREATE new value — resolve scope_id
            if ($this->scope === 'site') {
                $scopeId = $this->siteId;
            } else {
                $site = Site::find($this->siteId);
                if (! $site || ! $site->site_group_id) {
                    $this->addError('scope', 'Сайт не належить до жодної групи.');

                    return;
                }
                $scopeId = $site->site_group_id;
            }

            $dv = DataValue::create([
                'key'           => $this->key,
                'value_type_id' => $valueType->id,
                'scope_type'    => $this->scope,
                'scope_id'      => $scopeId,
                'content'       => $content,
                'status'        => 'active',
            ]);

            AuditLog::create([
                'actor_type'   => 'user',
                'action'       => 'value.created',
                'subject_type' => 'DataValue',
                'subject_id'   => $dv->id,
                'old'          => null,
                'new'          => $content,
            ]);

            if (! empty($this->geoTagIds)) {
                $dv->geoTags()->sync($this->geoTagIds);
            }

            if ($this->type === 'phone') {
                PhoneSlot::create([
                    'data_value_id'    => $dv->id,
                    'return_mode'      => 'auto',
                    'exhaustion_policy' => 'hide',
                ]);
                $this->dispatch('open-slot', dataValueId: $dv->id);
            }
        }

        $this->open = false;
        $this->dispatch('value-saved');
        $this->publishAffected($dv);
    }

    public function overrideForSite(int $valueId, int $siteId): void
    {
        $groupValue = DataValue::findOrFail($valueId);
        if (! $this->canChangeValue($groupValue) || ! $this->canChangeSite($siteId)) {
            return;
        }

        // Guard: only override group-scoped values
        if ($groupValue->scope_type !== 'group') {
            return;
        }

        // Guard: site must belong to the group
        $site = Site::find($siteId);
        if (! $site || $site->site_group_id !== $groupValue->scope_id) {
            return;
        }

        $siteValue = DataValue::create([
            'key'           => $groupValue->key,
            'value_type_id' => $groupValue->value_type_id,
            'scope_type'    => 'site',
            'scope_id'      => $siteId,
            'content'       => $groupValue->content,
            'status'        => 'active',
        ]);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'value.overridden',
            'subject_type' => 'DataValue',
            'subject_id'   => $siteValue->id,
            'old'          => ['scope_type' => 'group', 'scope_id' => $groupValue->scope_id],
            'new'          => ['scope_type' => 'site', 'scope_id' => $siteId],
        ]);

        $this->dispatch('value-saved');
        $this->publishAffected($siteValue);
    }

    public function delete(): void
    {
        $dv = DataValue::findOrFail($this->valueId);
        if (! $this->canDeleteValue($dv)) {
            return;
        }

        // Capture affected sites BEFORE deleting the row (AffectedSites needs the record).
        $affectedSites = app(AffectedSites::class)->for($dv);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'value.deleted',
            'subject_type' => 'DataValue',
            'subject_id'   => $dv->id,
            'old'          => $dv->content,
            'new'          => null,
        ]);

        $dv->delete();

        $this->valueId = null;
        $this->open    = false;
        $this->dispatch('value-saved');

        // Publish outside the transaction; a failed push is non-fatal.
        $published = 0;
        $affectedSites->each(function ($site) use (&$published) {
            $publication = app(SitePayloadCompiler::class)->publish($site);
            if (app(BridgePublisher::class)->push($publication)) {
                $published++;
            }
        });
        if ($published > 0) {
            $this->dispatch('toast', message: "Видалено → опубліковано {$published} сайтів");
        }
    }

    /** Публікує уражені сайти в DataBridge; невдалий push не валить операцію. */
    private function publishAffected(DataValue $dv): void
    {
        $published = 0;
        app(AffectedSites::class)->for($dv)->each(function ($site) use (&$published) {
            $publication = app(SitePayloadCompiler::class)->publish($site);
            if (app(BridgePublisher::class)->push($publication)) {
                $published++;
            }
        });
        if ($published > 0) {
            $this->dispatch('toast', message: "Збережено → опубліковано {$published} сайтів");
        }
    }

    private function messengerUrlFromValue(string $value): ?string
    {
        return preg_match('/^https?:\/\//i', $value) ? $value : null;
    }

    private function canSaveCurrentTarget(): bool
    {
        if ($this->valueId) {
            return $this->canChangeValue(DataValue::find($this->valueId));
        }

        if (! $this->siteId) {
            return false;
        }

        if ($this->scope === 'site') {
            return $this->canChangeSite($this->siteId);
        }

        $site = Site::find($this->siteId);
        if (! $site?->site_group_id) {
            return false;
        }

        $access = app(AccessControl::class);

        return $access->canEditGroup(auth()->user(), $site->site_group_id)
            && $access->canPublishGroup(auth()->user(), $site->site_group_id);
    }

    private function canChangeSite(int $siteId): bool
    {
        $access = app(AccessControl::class);

        return $access->canEditSite(auth()->user(), $siteId)
            && $access->canPublishSite(auth()->user(), $siteId);
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

    public function render()
    {
        $allGeoTags = GeoTag::orderBy('code')->get();

        $availableSlots = collect();
        if ($this->siteId) {
            $site = Site::find($this->siteId);
            $phoneTypeId = ValueType::where('code', 'phone')->value('id');
            if ($phoneTypeId && $site) {
                $availableSlots = DataValue::where('value_type_id', $phoneTypeId)
                    ->where(fn ($q) => $q
                        ->where(fn ($q2) => $q2->where('scope_type', 'site')->where('scope_id', $site->id))
                        ->orWhere(fn ($q2) => $q2->where('scope_type', 'group')->where('scope_id', $site->site_group_id))
                    )
                    ->pluck('key');
            }
        }

        return view('livewire.value-editor', [
            'allGeoTags'     => $allGeoTags,
            'availableSlots' => $availableSlots,
        ]);
    }
}
