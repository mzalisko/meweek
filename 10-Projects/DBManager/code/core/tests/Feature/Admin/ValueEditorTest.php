<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValueEditor;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValueEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_group_value(): void
    {
        $this->actingAs(User::factory()->create());
        $group = SiteGroup::factory()->create();
        $site = Site::factory()->for($group, 'group')->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'price')->set('key', 'price_basic')->set('value', '1200')->set('scope', 'group')
            ->call('save');

        $dv = DataValue::where('key', 'price_basic')->first();
        $this->assertNotNull($dv);
        $this->assertSame('group', $dv->scope_type);
        $this->assertSame($group->id, $dv->scope_id);
        $this->assertSame('1200', $dv->content['value']);
        $this->assertTrue(AuditLog::where('action', 'value.created')->exists());
    }

    public function test_create_site_value(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'text')->set('key', 'addr')->set('value', 'вул. Хрещатик, 1')->set('scope', 'site')
            ->call('save');

        $dv = DataValue::where('key', 'addr')->first();
        $this->assertSame('site', $dv->scope_type);
        $this->assertSame($site->id, $dv->scope_id);
    }

    public function test_validation_requires_key_and_type(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('key', '')->call('save')->assertHasErrors('key');
    }
}
