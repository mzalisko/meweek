<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValuesGrid;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class ValuesGridTest extends TestCase
{
    use RefreshDatabase, BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_shows_site_values_grouped_by_type(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->forSite($site)->create(['key' => 'price_basic', 'content' => ['value' => '1200']]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->assertSee('price_basic')
            ->assertSee('1200');
    }

    public function test_sections_render_in_stable_operational_order(): void
    {
        $site = Site::factory()->create();

        DataValue::factory()->forSite($site)->ofType('price')->create([
            'key' => 'price_basic',
            'content' => ['prices' => [['label' => 'UA', 'value' => '1200', 'geo' => ['UA']]]],
        ]);
        DataValue::factory()->forSite($site)->ofType('messenger')->create([
            'key' => 'telegram',
            'content' => ['value' => 'https://t.me/example', 'network' => 'telegram'],
        ]);
        DataValue::factory()->forSite($site)->ofType('phone')->create([
            'key' => 'phone_main',
            'content' => [],
        ]);

        $this->get(route('admin.site', ['site' => $site->id]))
            ->assertOk()
            ->assertSeeInOrder(['Телефони', 'Месенджери', 'Ціни']);
    }

    public function test_defaults_to_first_site_when_none_given(): void
    {
        $site = Site::factory()->create(['domain' => 'domen.ua']);
        DataValue::factory()->forSite($site)->create(['key' => 'k1']);

        Livewire::test(ValuesGrid::class)
            ->assertSee('domen.ua')
            ->assertSee('k1');
    }

    public function test_inline_add_phone_reserve(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']); // priority 0
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->set('newPhoneValue.' . $slot->data_value_id, '+380441112233')
            ->call('addPhoneReserve', $slot->data_value_id);

        $slot->refresh();
        $this->assertSame(2, $slot->entries()->count());
        $entry = $slot->entries()->orderByDesc('priority')->first();
        $this->assertSame(1, $entry->priority);
        $this->assertSame('+380441112233', $entry->phoneNumber->e164);
    }

    public function test_phone_exhaustion_policy_can_be_changed_from_grid(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->assertSee('Якщо всі впали')
            ->call('setPhoneExhaustionPolicy', $slot->data_value_id, 'emergency')
            ->call('savePhoneEmergencyNumber', $slot->data_value_id, '+380991112233');

        $slot->refresh();
        $this->assertSame('emergency', $slot->exhaustion_policy);
        $this->assertSame('+380991112233', $slot->emergency_number);
    }

    public function test_inline_reorder_phone_reserves(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active', 'active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('movePhoneUp', $entries[1]->id);

        $this->assertSame(0, $entries[1]->fresh()->priority);
        $this->assertSame(1, $entries[0]->fresh()->priority);
    }

    public function test_manual_sync_current_site_calls_bridge_publisher(): void
    {
        $site = Site::factory()->create();

        $this->mock(\App\Services\Publishing\BridgePublisher::class, function ($mock) {
            $mock->shouldReceive('push')->once()->andReturn(true);
        });

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('syncCurrentSite')
            ->assertDispatched('toast', message: 'Дані сайту успішно синхронізовано з плагіном');
    }

    public function test_manager_can_edit_site_details_directly_from_grid(): void
    {
        $site = Site::factory()->create([
            'name' => 'Old Name',
            'domain' => 'old-domain.com',
            'country_hint' => 'US',
        ]);

        // 1. A user without manage access (role 'viewer') cannot see the button or edit the site
        $viewer = User::factory()->create(['role' => \App\Admin\AccessControl::ROLE_VIEWER]);
        $this->actingAs($viewer);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->assertDontSee('Редагувати сайт');

        // 2. A user with manage access (role 'superadmin') can see the button and edit the site
        $admin = User::factory()->create(['role' => \App\Admin\AccessControl::ROLE_SUPERADMIN]);
        $this->actingAs($admin);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->assertSee('Редагувати сайт')
            ->call('editSite')
            ->assertSet('siteName', 'Old Name')
            ->assertSet('siteDomain', 'old-domain.com')
            ->assertSet('siteCountryHint', 'US')
            ->set('siteName', 'New Name')
            ->set('siteDomain', 'new-domain.com')
            ->set('siteCountryHint', 'UA')
            ->call('saveSite')
            ->assertDispatched('toast', message: 'Сайт збережено');

        $site->refresh();
        $this->assertSame('New Name', $site->name);
        $this->assertSame('new-domain.com', $site->domain);
        $this->assertSame('UA', $site->country_hint);
    }
}
