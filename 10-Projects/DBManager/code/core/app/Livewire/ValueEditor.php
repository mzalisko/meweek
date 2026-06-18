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

    public array $prices = [];

    public function updatedType(string $newType): void
    {
        if ($newType === 'price' && empty($this->prices)) {
            $this->addPrice();
        }
    }

    public function addPrice(): void
    {
        $this->prices[] = [
            'label' => '',
            'value' => '',
            'geo' => ['WORLD'],
        ];
    }

    public function removePrice(int $index): void
    {
        unset($this->prices[$index]);
        $this->prices = array_values($this->prices);
    }

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
        $this->prices = [];
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
        $this->prices    = $dv->content['prices'] ?? [];
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
            'type'  => ['required', $this->valueId ? 'in:text,price,messenger,address,social,phone' : 'in:phone,messenger,price'],
        ];

        if ($this->type !== 'phone' && $this->type !== 'price') {
            $rules['value'] = ['required'];
        }
        if ($this->type === 'messenger') {
            $rules['network'] = ['nullable', 'string', 'max:64'];
        }
        if ($this->type === 'price') {
            $rules['prices'] = ['required', 'array', 'min:1'];
            $rules['prices.*.value'] = ['required'];
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
        if ($this->type === 'price') {
            $content = [
                'prices' => $this->prices,
            ];

            // Auto-collect unique geo tag ids from price entries
            $geoCodes = [];
            foreach ($this->prices as $p) {
                foreach ($p['geo'] ?? [] as $code) {
                    $geoCodes[] = $code;
                }
            }
            $geoCodes = array_unique($geoCodes);
            $this->geoTagIds = GeoTag::whereIn('code', $geoCodes)->pluck('id')->toArray();
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
            // CREATE new value — сайт обов'язковий як база
            if (! $this->siteId) {
                $this->addError('key', 'Потрібен сайт для створення значення.');

                return;
            }

            $dv = DataValue::create([
                'key'           => $this->key,
                'value_type_id' => $valueType->id,
                'scope_type'    => 'site',
                'scope_id'      => (int) $this->siteId,
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

    public function delete(): void
    {
        $dv = DataValue::findOrFail($this->valueId);
        if (! $this->canDeleteValue($dv)) {
            return;
        }

        $affectedSites = app(AffectedSites::class)->for($dv);

        AuditLog::create([
            'actor_type'   => 'user',
            'action'       => 'value.deleted',
            'subject_type' => 'DataValue',
            'subject_id'   => $dv->id,
            'old'          => \App\Services\Audit\AuditRestorer::serializeValue($dv),
            'new'          => null,
        ]);

        $dv->delete();

        $this->valueId = null;
        $this->open    = false;
        $this->dispatch('value-saved');

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

        return $this->canChangeSite($this->siteId);
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
                    ->where('scope_type', 'site')
                    ->where('scope_id', $site->id)
                    ->pluck('key');
            }
        }

        return view('livewire.value-editor', [
            'allGeoTags'     => $allGeoTags,
            'availableSlots' => $availableSlots,
        ]);
    }
}
