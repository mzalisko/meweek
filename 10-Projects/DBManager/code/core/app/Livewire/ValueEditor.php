<?php

namespace App\Livewire;

use App\Admin\AffectedSites;
use App\Admin\AccessControl;
use App\Livewire\Concerns\UsesEditLock;
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
    use UsesEditLock;

    public bool $open = false;

    #[On('close-editor-panel')]
    public function closePanel(): void
    {
        $this->open = false;
        $this->releaseEditLock();
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

    // Адреса (структурований тип A+ — поля + похідне value-дзеркало)
    public ?string $addrCountry = null;

    public ?string $addrRegion = null;

    public ?string $addrCity = null;

    public ?string $addrStreet = null;

    public ?string $addrPostcode = null;

    // Підсвічування single-edit: оригінальне відображуване значення до правки
    public ?string $originalValue = null;

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

        $this->releaseEditLock();
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
        $this->addrCountry = null;
        $this->addrRegion = null;
        $this->addrCity = null;
        $this->addrStreet = null;
        $this->addrPostcode = null;
        $this->originalValue = null;
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
        $this->addrCountry  = $dv->content['country'] ?? null;
        $this->addrRegion   = $dv->content['region'] ?? null;
        $this->addrCity     = $dv->content['city'] ?? null;
        $this->addrStreet   = $dv->content['street'] ?? null;
        $this->addrPostcode = $dv->content['postcode'] ?? null;
        $this->originalValue = $dv->content['value'] ?? ($dv->content['name'] ?? null);
        $this->resetValidation();
        $this->dispatch('close-messenger-panel');
        $this->dispatch('close-slot-panel');
        $this->open    = true;

        $this->acquireEditLock($this->editLockKey('data-value', $dv->id), $dv->key);
    }

    public function save(): void
    {
        if (! $this->ensureEditLock()) {
            return;
        }

        if (! $this->canSaveCurrentTarget()) {
            return;
        }

        // Для цін потрібен окремий дозвіл can_view_prices
        if ($this->type === 'price' && ! app(AccessControl::class)->canViewPrices(auth()->user(), $this->siteId)) {
            return;
        }

        // Дозволені типи беремо з реєстру value_types (єдина точка істини),
        // тож усі засіяні типи (incl. text/address/social) і створюються, і редагуються.
        $rules = [
            'key'   => ['required', 'regex:/^[a-z0-9_]+$/'],
            'type'  => ['required', 'in:'.ValueType::pluck('code')->implode(',')],
        ];

        if (! in_array($this->type, ['phone', 'price', 'address'], true)) {
            $rules['value'] = ['required'];
        }
        if ($this->type === 'messenger') {
            $rules['network'] = ['nullable', 'string', 'max:64'];
        }
        if ($this->type === 'social') {
            $rules['network'] = ['required', 'string', 'max:64'];
        }
        if ($this->type === 'address') {
            $rules['addrCity']     = ['required', 'string', 'max:120'];
            $rules['addrCountry']  = ['nullable', 'string', 'max:120'];
            $rules['addrRegion']   = ['nullable', 'string', 'max:120'];
            $rules['addrStreet']   = ['nullable', 'string', 'max:200'];
            $rules['addrPostcode'] = ['nullable', 'string', 'max:32'];
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
        if ($this->type === 'social') {
            $content = [
                'value'   => $this->value !== '' ? $this->value : null,
                'network' => $this->network,
                'url'     => $this->url ?: $this->messengerUrlFromValue($this->value),
            ];
        }
        if ($this->type === 'address') {
            $content = $this->buildAddressContent();
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
        $this->releaseEditLock();
        $this->dispatch('value-saved');
        $this->publishAffected($dv);
    }

    public function delete(): void
    {
        if (! $this->ensureEditLock()) {
            return;
        }

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
        $this->releaseEditLock();
        $this->dispatch('value-saved');

        $affectedSites->each(function ($site) {
            app(SitePayloadCompiler::class)->publish($site);
        });
        $this->dispatch('toast', message: "Видалено значення");
    }

    /** Публікує уражені сайти локально; синхронізація робиться вручну. */
    private function publishAffected(DataValue $dv): void
    {
        app(AffectedSites::class)->for($dv)->each(function ($site) {
            app(SitePayloadCompiler::class)->publish($site);
        });
        $this->dispatch('toast', message: "Збережено зміни");
    }

    private function messengerUrlFromValue(string $value): ?string
    {
        return preg_match('/^https?:\/\//i', $value) ? $value : null;
    }

    /**
     * Поточне відображуване значення (для підсвічування «Було → стало» в single-edit).
     * Для адреси — похідне value-дзеркало зі структурованих полів.
     */
    public function currentDisplayValue(): ?string
    {
        if ($this->type === 'address') {
            return $this->buildAddressContent()['value'];
        }

        return $this->value !== '' ? $this->value : null;
    }

    /** Чи відрізняється поточне значення від збереженого (single-edit dirty). */
    public function isValueDirty(): bool
    {
        return $this->valueId !== null
            && $this->originalValue !== null
            && $this->originalValue !== $this->currentDisplayValue();
    }

    /** Структурована адреса (A+): структуровані поля + похідне value-дзеркало для generic-механізмів. */
    private function buildAddressContent(): array
    {
        $parts = array_values(array_filter(
            [$this->addrStreet, $this->addrCity, $this->addrRegion, $this->addrPostcode, $this->addrCountry],
            fn ($p) => $p !== null && trim((string) $p) !== ''
        ));

        return [
            'country'  => $this->addrCountry !== '' ? $this->addrCountry : null,
            'region'   => $this->addrRegion !== '' ? $this->addrRegion : null,
            'city'     => $this->addrCity !== '' ? $this->addrCity : null,
            'street'   => $this->addrStreet !== '' ? $this->addrStreet : null,
            'postcode' => $this->addrPostcode !== '' ? $this->addrPostcode : null,
            'value'    => $parts ? implode(', ', $parts) : null,
        ];
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
