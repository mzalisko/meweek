<?php

namespace Tests\Feature\Admin;

use App\Admin\SiteGridReader;
use App\Livewire\MessengerPanel;
use App\Livewire\ValuesGrid;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SlotMessengerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_messenger_panel_adds_reserve_to_independent_messenger_slot(): void
    {
        $site = Site::factory()->create();
        $ua = GeoTag::where('code', 'UA')->firstOrFail();
        $main = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => [
                'value' => 'https://t.me/main',
                'network' => 'telegram',
                'url' => 'https://t.me/main',
                'enabled' => true,
                'exhaustion_policy' => 'hide',
            ],
        ]);
        $main->geoTags()->sync([$ua->id]);

        Livewire::test(MessengerPanel::class)
            ->call('open', $main->id)
            ->set('newValue', 'https://t.me/backup')
            ->call('addReserve')
            ->assertSet('newValue', '');

        $reserve = DataValue::whereHas('type', fn ($q) => $q->where('code', 'messenger'))
            ->where('id', '!=', $main->id)
            ->sole();

        $this->assertSame('telegram', $reserve->content['network']);
        $this->assertSame('https://t.me/backup', $reserve->content['value']);
        $this->assertSame('https://t.me/backup', $reserve->content['url']);
        $this->assertSame('tg_brand', $reserve->content['messenger_slot']);
        $this->assertSame(['UA'], $reserve->geoTags->pluck('code')->all());
        $this->assertTrue(AuditLog::where('action', 'messenger.reserve_added')->exists());
    }

    public function test_messenger_grid_shows_reserves_as_chain_rows(): void
    {
        $site = Site::factory()->create();
        $main = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Main TG', 'network' => 'telegram', 'enabled' => true],
        ]);
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand_1',
            'content' => ['value' => 'Backup TG', 'network' => 'telegram', 'messenger_slot' => 'tg_brand', 'enabled' => true],
        ]);

        $row = app(SiteGridReader::class)->forSite($site)['messenger'][0];

        $this->assertSame('tg_brand', $row['key']);
        $this->assertSame('Main TG', $row['name']);
        $this->assertSame('ok', $row['state']);
        $this->assertSame(1, $row['reserves']);
        $this->assertSame('#1.1', $row['reserve_rows'][0]['label']);
        $this->assertSame('Backup TG', $row['reserve_rows'][0]['value']);
        $this->assertSame($main->id, $row['id']);
    }

    public function test_deactivating_main_messenger_switches_to_reserve_without_moving_labels(): void
    {
        $site = Site::factory()->create();
        $main = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Main TG', 'network' => 'telegram', 'enabled' => true],
        ]);
        $reserve = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand_1',
            'content' => ['value' => 'Backup TG', 'network' => 'telegram', 'messenger_slot' => 'tg_brand', 'enabled' => true],
        ]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('deactivateMessenger', $main->id);

        $row = app(SiteGridReader::class)->forSite($site)['messenger'][0];

        $this->assertFalse($main->fresh()->content['enabled']);
        $this->assertSame('on_reserve', $row['state']);
        $this->assertSame('tg_brand', $row['key']);
        $this->assertSame('Backup TG', $row['value']);
        $this->assertSame('Main TG', $row['name']);
        $this->assertSame($reserve->id, $row['reserve_rows'][0]['id']);
        $this->assertSame('on_reserve', $row['reserve_rows'][0]['state']);
    }

    public function test_pin_reserve_messenger_marks_it_current(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Main TG', 'network' => 'telegram', 'enabled' => true],
        ]);
        $reserve = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand_1',
            'content' => ['value' => 'Backup TG', 'network' => 'telegram', 'messenger_slot' => 'tg_brand', 'enabled' => true],
        ]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('pinMessenger', $reserve->id);

        $row = app(SiteGridReader::class)->forSite($site)['messenger'][0];

        $this->assertTrue($reserve->fresh()->content['pinned']);
        $this->assertSame('pinned', $row['state']);
        $this->assertSame('Backup TG', $row['value']);
        $this->assertTrue($row['reserve_rows'][0]['is_current']);
    }

    public function test_messenger_panel_updates_policy_for_whole_group(): void
    {
        $site = Site::factory()->create();
        $main = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Main TG', 'network' => 'telegram', 'enabled' => true, 'exhaustion_policy' => 'hide'],
        ]);
        $reserve = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand_1',
            'content' => ['value' => 'Backup TG', 'network' => 'telegram', 'messenger_slot' => 'tg_brand', 'enabled' => true, 'exhaustion_policy' => 'hide'],
        ]);

        Livewire::test(MessengerPanel::class)
            ->call('open', $main->id)
            ->call('setExhaustionPolicy', 'last');

        $this->assertSame('last', $main->fresh()->content['exhaustion_policy']);
        $this->assertSame('last', $reserve->fresh()->content['exhaustion_policy']);
    }

    public function test_messenger_panel_updates_return_mode_for_whole_group(): void
    {
        $site = Site::factory()->create();
        $main = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Main TG', 'network' => 'telegram', 'enabled' => true, 'return_mode' => 'auto'],
        ]);
        $reserve = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand_1',
            'content' => ['value' => 'Backup TG', 'network' => 'telegram', 'messenger_slot' => 'tg_brand', 'enabled' => true, 'return_mode' => 'auto'],
        ]);

        Livewire::test(MessengerPanel::class)
            ->call('open', $main->id)
            ->call('setReturnMode', 'sticky');

        $this->assertSame('sticky', $main->fresh()->content['return_mode']);
        $this->assertSame('sticky', $reserve->fresh()->content['return_mode']);
    }

    public function test_emergency_messenger_value_is_visible_when_all_messengers_are_disabled(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => [
                'value' => 'Main TG',
                'network' => 'telegram',
                'enabled' => false,
                'exhaustion_policy' => 'emergency',
                'emergency_value' => 'https://t.me/emergency',
            ],
        ]);
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand_1',
            'content' => [
                'value' => 'Backup TG',
                'network' => 'telegram',
                'messenger_slot' => 'tg_brand',
                'enabled' => false,
                'exhaustion_policy' => 'emergency',
                'emergency_value' => 'https://t.me/emergency',
            ],
        ]);

        $row = app(SiteGridReader::class)->forSite($site)['messenger'][0];

        $this->assertSame('exhausted', $row['state']);
        $this->assertSame('https://t.me/emergency', $row['value']);
        $this->assertSame('https://t.me/emergency', $row['emergency_value']);
    }

    public function test_messenger_panel_can_hide_and_show_whole_slot(): void
    {
        $site = Site::factory()->create();
        $main = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Main TG', 'network' => 'telegram', 'enabled' => true],
        ]);
        $reserve = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand_1',
            'content' => ['value' => 'Backup TG', 'network' => 'telegram', 'messenger_slot' => 'tg_brand', 'enabled' => true],
        ]);

        Livewire::test(MessengerPanel::class)
            ->call('open', $main->id)
            ->call('hideSlot');

        $this->assertSame('hidden', $main->fresh()->status);
        $this->assertSame('hidden', $reserve->fresh()->status);

        Livewire::test(MessengerPanel::class)
            ->call('open', $main->id)
            ->call('showSlot');

        $this->assertSame('active', $main->fresh()->status);
        $this->assertSame('active', $reserve->fresh()->status);
    }

    public function test_values_grid_opens_independent_messenger_panel(): void
    {
        $site = Site::factory()->create();
        $main = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Main TG', 'network' => 'telegram', 'enabled' => true],
        ]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('openMessengerSlot', $main->id)
            ->assertDispatched('open-messenger-slot', dataValueId: $main->id);
    }
}
