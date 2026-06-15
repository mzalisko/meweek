<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\ValueType;
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
    }

    public function delete(): void
    {
        $dv = DataValue::findOrFail($this->valueId);

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
    }

    public function render()
    {
        return view('livewire.value-editor');
    }
}
