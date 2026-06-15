<?php

namespace App\Livewire;

use App\Admin\AffectedSites;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\ValueType;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Livewire\Attributes\On;
use Livewire\Component;

class ValueEditor extends Component
{
    public bool $open = false;

    public ?int $siteId = null;

    public ?int $valueId = null;

    public string $type = 'text';

    public string $key = '';

    public string $value = '';

    public string $scope = 'site';

    public ?string $network = null;

    public ?string $url = null;

    #[On('open-value-editor')]
    public function openCreate(int $siteId): void
    {
        $this->createFor($siteId);
    }

    public function createFor(int $siteId): void
    {
        $this->siteId = $siteId;
        $this->valueId = null;
        $this->type = 'text';
        $this->key = '';
        $this->value = '';
        $this->scope = 'site';
        $this->network = null;
        $this->url = null;
        $this->resetValidation();
        $this->open = true;
    }

    #[On('edit-value')]
    public function edit(int $valueId): void
    {
        $dv = DataValue::findOrFail($valueId);

        $this->valueId = $dv->id;
        $this->siteId  = $dv->scope_type === 'site' ? $dv->scope_id : null;
        $this->type    = $dv->type->code;
        $this->key     = $dv->key;
        $this->value   = $dv->content['value'] ?? '';
        $this->scope   = $dv->scope_type;
        $this->network = $dv->content['network'] ?? null;
        $this->url     = $dv->content['url'] ?? null;
        $this->resetValidation();
        $this->open    = true;
    }

    public function save(): void
    {
        $this->validate([
            'key'   => ['required', 'regex:/^[a-z0-9_]+$/'],
            'type'  => ['required', 'in:text,price,messenger,address,social'],
            'scope' => ['required', 'in:group,site'],
        ]);

        // Build content
        $content = ['value' => $this->value];
        if ($this->type === 'messenger') {
            $content['network'] = $this->network;
            $content['url']     = $this->url;
        }

        // Resolve value_type_id
        $valueType = ValueType::firstOrCreate(
            ['code' => $this->type],
            ['name' => $this->type],
        );

        if ($this->valueId) {
            // UPDATE existing value
            $dv      = DataValue::findOrFail($this->valueId);
            $oldContent = $dv->content;

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
        }

        $this->open = false;
        $this->dispatch('value-saved');
        $this->publishAffected($dv);
    }

    public function overrideForSite(int $valueId, int $siteId): void
    {
        $groupValue = DataValue::findOrFail($valueId);

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

    public function render()
    {
        return view('livewire.value-editor');
    }
}
