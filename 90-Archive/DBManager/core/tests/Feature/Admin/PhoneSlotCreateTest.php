<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValueEditor;
use App\Models\DataValue;
use App\Models\PhoneSlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhoneSlotCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_phone_value_creates_slot_and_opens_panel(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'phone')
            ->set('key', 'phone_ua_1')
            ->set('scope', 'site')
            ->call('save')
            ->assertDispatched('open-slot');

        $dv = DataValue::where('key', 'phone_ua_1')->first();
        $this->assertNotNull($dv);
        $this->assertSame('phone', $dv->type->code);
        $this->assertSame('site', $dv->scope_type);
        $slot = PhoneSlot::where('data_value_id', $dv->id)->first();
        $this->assertNotNull($slot);
        $this->assertSame('auto', $slot->return_mode);
        $this->assertSame('hide', $slot->exhaustion_policy);
    }

    public function test_phone_value_does_not_require_value_field(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'phone')->set('key', 'phone_x')->set('scope', 'site')
            ->call('save')
            ->assertHasNoErrors();
    }
}
