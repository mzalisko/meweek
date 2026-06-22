<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValueEditor;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValueEditorLinkedSlotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_save_messenger_does_not_create_phone_link(): void
    {
        $site = Site::factory()->create();

        $dv = DataValue::factory()->forSite($site)->ofType('messenger')->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Telegram', 'network' => 'telegram'],
        ]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('value', 'https://t.me/brand')
            ->set('network', 'telegram')
            ->call('save');

        $content = $dv->fresh()->content;

        $this->assertSame('https://t.me/brand', $content['value']);
        $this->assertSame('https://t.me/brand', $content['url']);
        $this->assertArrayNotHasKey('linked_slot', $content);
    }

    public function test_save_messenger_preserves_existing_messenger_slot_group(): void
    {
        $site = Site::factory()->create();

        $dv = DataValue::factory()->forSite($site)->ofType('messenger')->create([
            'key' => 'tg_brand_1',
            'content' => [
                'value' => 'Backup',
                'network' => 'telegram',
                'messenger_slot' => 'tg_brand',
                'enabled' => true,
                'pinned' => true,
                'exhaustion_policy' => 'last',
            ],
        ]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('value', 'Backup changed')
            ->set('network', 'telegram')
            ->call('save');

        $content = $dv->fresh()->content;

        $this->assertSame('Backup changed', $content['value']);
        $this->assertSame('tg_brand', $content['messenger_slot']);
        $this->assertTrue($content['enabled']);
        $this->assertTrue($content['pinned']);
        $this->assertSame('last', $content['exhaustion_policy']);
    }
}
