<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValueEditor;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use App\Models\ValueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValueEditorEditTest extends TestCase
{
    use RefreshDatabase;

    private function makeValue(array $attrs = []): DataValue
    {
        $type = ValueType::firstOrCreate(['code' => 'text'], ['name' => 'text']);
        $site = Site::factory()->create();

        return DataValue::create(array_merge([
            'key'           => 'test_key',
            'value_type_id' => $type->id,
            'scope_type'    => 'site',
            'scope_id'      => $site->id,
            'content'       => ['value' => 'old_val'],
            'status'        => 'active',
        ], $attrs));
    }

    public function test_edit_loads_value_into_form(): void
    {
        $this->actingAs(User::factory()->create());
        $dv = $this->makeValue(['content' => ['value' => 'hello']]);

        $component = Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id);

        $component
            ->assertSet('valueId', $dv->id)
            ->assertSet('key', 'test_key')
            ->assertSet('value', 'hello')
            ->assertSet('type', 'text')
            ->assertSet('scope', 'site')
            ->assertSet('open', true);
    }

    public function test_edit_loads_messenger_text_network_and_url(): void
    {
        $this->actingAs(User::factory()->create());
        $type = ValueType::firstOrCreate(['code' => 'messenger'], ['name' => 'messenger']);
        $site = Site::factory()->create();
        $dv = DataValue::create([
            'key'           => 'tg_main',
            'value_type_id' => $type->id,
            'scope_type'    => 'site',
            'scope_id'      => $site->id,
            'content'       => ['value' => 'Написати в Telegram', 'network' => 'telegram', 'url' => 'https://t.me/mybot'],
            'status'        => 'active',
        ]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->assertSet('value', 'Написати в Telegram')
            ->assertSet('network', 'telegram')
            ->assertSet('url', 'https://t.me/mybot');
    }

    public function test_save_persists_messenger_text_and_url(): void
    {
        $this->actingAs(User::factory()->create());
        $type = ValueType::firstOrCreate(['code' => 'messenger'], ['name' => 'messenger']);
        $site = Site::factory()->create();
        $dv = DataValue::create([
            'key'           => 'tg_main',
            'value_type_id' => $type->id,
            'scope_type'    => 'site',
            'scope_id'      => $site->id,
            'content'       => ['value' => 'Написати в Telegram', 'network' => 'telegram'],
            'status'        => 'active',
        ]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('value', 'Написати в Support')
            ->set('url', 'https://t.me/support_bot')
            ->call('save');

        $fresh = $dv->fresh();
        $this->assertSame('Написати в Support', $fresh->content['value'] ?? null);
        $this->assertSame('https://t.me/support_bot', $fresh->content['url'] ?? null);
    }

    public function test_save_updates_existing_value_and_audits(): void
    {
        $this->actingAs(User::factory()->create());
        $dv = $this->makeValue(['content' => ['value' => 'old_val']]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('value', 'new_val')
            ->call('save');

        $dv->refresh();
        $this->assertSame('new_val', $dv->content['value']);

        $audit = AuditLog::where('action', 'value.updated')
            ->where('subject_type', 'DataValue')
            ->where('subject_id', $dv->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('old_val', $audit->old['value']);
        $this->assertSame('new_val', $audit->new['value']);
    }

    public function test_save_closes_modal_and_dispatches_value_saved_on_update(): void
    {
        $this->actingAs(User::factory()->create());
        $dv = $this->makeValue();

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('value', 'changed')
            ->call('save')
            ->assertSet('open', false)
            ->assertDispatched('value-saved');
    }

    public function test_delete_removes_value_and_audits(): void
    {
        $this->actingAs(User::factory()->create());
        $dv = $this->makeValue(['content' => ['value' => 'to_delete']]);
        $dvId = $dv->id;

        Livewire::test(ValueEditor::class)
            ->call('edit', $dvId)
            ->call('delete');

        $this->assertNull(DataValue::find($dvId));

        $audit = AuditLog::where('action', 'value.deleted')
            ->where('subject_type', 'DataValue')
            ->where('subject_id', $dvId)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('to_delete', $audit->old['value']);
    }

    public function test_delete_dispatches_value_saved_and_closes(): void
    {
        $this->actingAs(User::factory()->create());
        $dv = $this->makeValue();

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->call('delete')
            ->assertSet('open', false)
            ->assertDispatched('value-saved');
    }
}
