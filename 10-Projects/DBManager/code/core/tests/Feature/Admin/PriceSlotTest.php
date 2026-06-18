<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValueEditor;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use App\Models\User;
use App\Services\Publishing\SitePayloadCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriceSlotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GeoTag::firstOrCreate(['code' => 'WORLD'], ['name' => 'World']);
        GeoTag::firstOrCreate(['code' => 'UA'], ['name' => 'Ukraine']);
        GeoTag::firstOrCreate(['code' => 'RO'], ['name' => 'Romania']);
    }

    public function test_create_price_slot_with_multiple_entries_and_geo_sync(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superadmin']));
        $site = Site::factory()->create();

        $prices = [
            ['label' => 'Україна', 'value' => '1200', 'geo' => ['UA']],
            ['label' => 'Румунія', 'value' => '2000', 'geo' => ['RO', 'WORLD']],
        ];

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->set('type', 'price')
            ->set('key', 'price_romania')
            ->set('prices', $prices)
            ->call('save')
            ->assertHasNoErrors();

        $dv = DataValue::where('key', 'price_romania')->first();
        $this->assertNotNull($dv);
        $this->assertSame('price', $dv->type->code);
        $this->assertEquals($prices, $dv->content['prices']);

        // Check if geo pivot is synced correctly
        $geoCodes = $dv->geoTags->pluck('code')->all();
        $this->assertContains('UA', $geoCodes);
        $this->assertContains('RO', $geoCodes);
        $this->assertContains('WORLD', $geoCodes);

        // Check audit log
        $log = AuditLog::where('subject_type', 'DataValue')->where('subject_id', $dv->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('value.created', $log->action);
        $this->assertEquals($prices, $log->new['prices']);
    }

    public function test_compile_price_slot_produces_multiple_payload_values(): void
    {
        $site = Site::factory()->create(['domain' => 'testsite.com']);
        $dv = DataValue::create([
            'key' => 'ROMANIA',
            'value_type_id' => \App\Models\ValueType::firstOrCreate(['code' => 'price'], ['name' => 'Ціна'])->id,
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'content' => [
                'prices' => [
                    ['label' => 'Укрїна', 'value' => '1200', 'geo' => ['UA']],
                    ['label' => 'Румінія', 'value' => '2000', 'geo' => ['WORLD', 'RU', 'BY']],
                ]
            ],
            'status' => 'active',
        ]);

        $compiler = app(SitePayloadCompiler::class);
        $payload = $compiler->compile($site);

        $values = $payload['values'];
        // Should have compiled two values with key ROMANIA
        $compiledPrices = array_values(array_filter($values, fn($v) => $v['key'] === 'ROMANIA'));
        
        $this->assertCount(2, $compiledPrices);
        
        $this->assertSame('1200', $compiledPrices[0]['value']);
        $this->assertEquals(['UA'], $compiledPrices[0]['geo']);
        
        $this->assertSame('2000', $compiledPrices[1]['value']);
        $this->assertEquals(['WORLD', 'RU', 'BY'], $compiledPrices[1]['geo']);
    }
}
