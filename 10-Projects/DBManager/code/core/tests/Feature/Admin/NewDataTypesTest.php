<?php

namespace Tests\Feature\Admin;

use App\Livewire\AuditManager;
use App\Livewire\BulkOperations;
use App\Livewire\ValueEditor;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use App\Services\Publishing\SitePayloadCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Нові типи даних (соцмережі, адреси) + добитий текст мають проходити КОЖЕН механізм,
 * а не лише CRUD. Тест падає, якщо новий тип десь «випав».
 */
class NewDataTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function itemByKey(array $payload, string $key): ?array
    {
        foreach ($payload['values'] as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    // --- ФУНДАМЕНТ: створення раніше заблокованих типів ---

    public function test_social_can_be_created_through_editor(): void
    {
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'social')
            ->set('key', 'tg_brand')
            ->set('value', '@brand')
            ->set('network', 'telegram')
            ->call('save')
            ->assertHasNoErrors();

        $dv = DataValue::where('key', 'tg_brand')->first();
        $this->assertNotNull($dv);
        $this->assertSame('social', $dv->type->code);
        $this->assertSame('@brand', $dv->content['value']);
        $this->assertSame('telegram', $dv->content['network']);
    }

    public function test_text_can_be_created_through_editor(): void
    {
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'text')
            ->set('key', 'note')
            ->set('value', 'Будь-який текст')
            ->call('save')
            ->assertHasNoErrors();

        $dv = DataValue::where('key', 'note')->first();
        $this->assertSame('text', $dv->type->code);
        $this->assertSame('Будь-який текст', $dv->content['value']);
    }

    public function test_address_can_be_created_with_structured_content_and_value_mirror(): void
    {
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'address')
            ->set('key', 'office')
            ->set('addrCity', 'Київ')
            ->set('addrStreet', 'вул. Хрещатик, 1')
            ->set('addrCountry', 'Україна')
            ->set('addrPostcode', '01001')
            ->call('save')
            ->assertHasNoErrors();

        $dv = DataValue::where('key', 'office')->first();
        $this->assertSame('address', $dv->type->code);
        $this->assertSame('Київ', $dv->content['city']);
        $this->assertSame('вул. Хрещатик, 1', $dv->content['street']);
        $this->assertSame('Україна', $dv->content['country']);
        $this->assertSame('01001', $dv->content['postcode']);
        // value-дзеркало склеєне для generic-механізмів
        $this->assertStringContainsString('Київ', $dv->content['value']);
        $this->assertStringContainsString('Хрещатик', $dv->content['value']);
    }

    // --- ВАЛІДАЦІЯ ---

    public function test_social_requires_network(): void
    {
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'social')
            ->set('key', 'soc_bad')
            ->set('value', '@x')
            ->set('network', '')
            ->call('save')
            ->assertHasErrors('network');
    }

    public function test_address_requires_city(): void
    {
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'address')
            ->set('key', 'addr_bad')
            ->set('addrStreet', 'вул. без міста')
            ->call('save')
            ->assertHasErrors('addrCity');
    }

    // --- РЕДАГУВАННЯ (round-trip) ---

    public function test_address_edit_loads_structured_fields(): void
    {
        $site = Site::factory()->create();
        $dv = DataValue::factory()->ofType('address')->forSite($site)->create([
            'key' => 'addr_edit',
            'content' => ['city' => 'Львів', 'street' => 'пл. Ринок', 'country' => 'Україна', 'value' => 'пл. Ринок, Львів, Україна'],
        ]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->assertSet('type', 'address')
            ->assertSet('addrCity', 'Львів')
            ->assertSet('addrStreet', 'пл. Ринок');
    }

    // --- ПУБЛІКАЦІЯ ---

    public function test_social_publishes_with_network_value_url(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->ofType('social')->forSite($site)->create([
            'key' => 'ig_brand',
            'content' => ['value' => '@brand', 'network' => 'instagram', 'url' => 'https://instagram.com/brand'],
        ]);

        $payload = app(SitePayloadCompiler::class)->compile($site);
        $item = $this->itemByKey($payload, 'ig_brand');

        $this->assertNotNull($item);
        $this->assertSame('social', $item['type']);
        $this->assertSame('instagram', $item['network']);
        $this->assertSame('@brand', $item['value']);
        $this->assertSame('https://instagram.com/brand', $item['url']);
    }

    public function test_address_publishes_structured_fields_without_leaking_keys(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->ofType('address')->forSite($site)->create([
            'key' => 'office_pub',
            'content' => [
                'city' => 'Київ', 'street' => 'Хрещатик 1', 'country' => 'Україна',
                'region' => null, 'postcode' => '01001', 'value' => 'Хрещатик 1, Київ, 01001, Україна',
                'internal_draft' => 'СЛУЖБОВЕ',
            ],
        ]);

        $payload = app(SitePayloadCompiler::class)->compile($site);
        $item = $this->itemByKey($payload, 'office_pub');

        $this->assertSame('address', $item['type']);
        $this->assertSame('Київ', $item['city']);
        $this->assertSame('Хрещатик 1', $item['street']);
        $this->assertSame('01001', $item['postcode']);
        // службові ключі content НЕ потрапляють у payload (явний allow-list)
        $this->assertArrayNotHasKey('internal_draft', $item);
    }

    // --- BULK ---

    public function test_bulk_blocks_text_operations_on_structured_address(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->ofType('address')->forSite($site)->create([
            'key' => 'addr_bulk', 'content' => ['city' => 'Київ', 'value' => 'Київ'],
        ]);

        Livewire::test(BulkOperations::class)
            ->set('targetType', 'address')
            ->set('operation', 'set_value')
            ->set('contentValue', 'НЕ МОЖНА')
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertHasErrors('operation');
    }

    public function test_bulk_set_geo_works_on_address(): void
    {
        $group = SiteGroup::factory()->create(['name' => 'Addr group']);
        $site = Site::factory()->for($group, 'group')->create(['domain' => 'addr.test']);
        GeoTag::firstOrCreate(['code' => 'UA'], ['name' => 'Україна']);

        $dv = DataValue::factory()->ofType('address')->forSite($site)->create([
            'key' => 'addr_geo', 'content' => ['city' => 'Київ', 'value' => 'Київ'],
        ]);

        Livewire::test(BulkOperations::class)
            ->set('scope', 'group')
            ->set('groupId', $group->id)
            ->set('targetType', 'address')
            ->set('operation', 'set_geo')
            ->set('geoCodes', ['UA'])
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertHasNoErrors()
            ->assertSet('report.changed', 1);

        $this->assertSame(['UA'], $dv->fresh('geoTags')->geoTags->pluck('code')->all());
    }

    public function test_bulk_set_value_works_on_social(): void
    {
        $group = SiteGroup::factory()->create(['name' => 'Soc group']);
        $site = Site::factory()->for($group, 'group')->create(['domain' => 'soc.test']);

        $dv = DataValue::factory()->ofType('social')->forSite($site)->create([
            'key' => 'soc_bulk', 'content' => ['value' => '@old', 'network' => 'telegram'],
        ]);

        Livewire::test(BulkOperations::class)
            ->set('scope', 'group')
            ->set('groupId', $group->id)
            ->set('targetType', 'social')
            ->set('operation', 'set_value')
            ->set('contentValue', '@new')
            ->set('publishAfterApply', false)
            ->call('apply')
            ->assertHasNoErrors();

        $this->assertSame('@new', $dv->fresh()->content['value']);
    }

    // --- АУДИТ (відображення типу даних, не «Текст») ---

    public function test_audit_labels_new_types_correctly(): void
    {
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'social')->set('key', 'soc_audit')->set('value', '@a')->set('network', 'telegram')
            ->call('save')->assertHasNoErrors();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'address')->set('key', 'addr_audit')->set('addrCity', 'Київ')
            ->call('save')->assertHasNoErrors();

        // Детальний перегляд логів конкретного сайту (де рендериться мітка «Тип даних»).
        Livewire::test(AuditManager::class)
            ->set('activeTab', 'changes')
            ->set('selectedSiteId', $site->id)
            ->assertSee('Соцмережа')
            ->assertSee('Адреса');
    }
}
