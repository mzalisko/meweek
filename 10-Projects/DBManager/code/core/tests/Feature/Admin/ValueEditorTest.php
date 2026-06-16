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
            ->set('type', 'messenger')->set('key', 'tg_brand')->set('value', 'https://t.me/brand')->set('network', 'telegram')->set('scope', 'group')
            ->call('save');

        $dv = DataValue::where('key', 'tg_brand')->first();
        $this->assertNotNull($dv);
        $this->assertSame('group', $dv->scope_type);
        $this->assertSame($group->id, $dv->scope_id);
        $this->assertSame('https://t.me/brand', $dv->content['value']);
        $this->assertTrue(AuditLog::where('action', 'value.created')->exists());
    }

    public function test_create_site_value(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'messenger')->set('key', 'tg_site')->set('value', 'https://t.me/site')->set('network', 'telegram')->set('scope', 'site')
            ->call('save');

        $dv = DataValue::where('key', 'tg_site')->first();
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
