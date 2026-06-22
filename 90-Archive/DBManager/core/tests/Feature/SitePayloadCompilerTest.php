<?php

namespace Tests\Feature;

use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Publication;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Services\Failover\FailoverEngine;
use App\Services\Publishing\SitePayloadCompiler;
use App\Support\PhoneFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SitePayloadCompilerTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    private function itemByKey(array $payload, string $key): ?array
    {
        foreach ($payload['values'] as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    public function test_phone_slot_compiles_to_active_number_with_geo(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update([
            'key' => 'phone_ua_1', 'scope_type' => 'site', 'scope_id' => $site->id,
        ]);
        $slot->dataValue->geoTags()->attach(GeoTag::where('code', 'UA')->first());

        $payload = app(SitePayloadCompiler::class)->compile($site);
        $item = $this->itemByKey($payload, 'phone_ua_1');

        $this->assertSame('phone', $item['type']);
        $this->assertSame(['UA'], $item['geo']);
        $this->assertSame('ok', $item['state']);
        $this->assertSame($entries[0]->phoneNumber->e164, $item['value']);
    }

    public function test_phone_slot_publishes_display_value_without_changing_raw_number(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update([
            'key' => 'phone_formatted',
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'content' => ['phone_format' => '+### (##) ###-##-##'],
        ]);

        $payload = app(SitePayloadCompiler::class)->compile($site);
        $item = $this->itemByKey($payload, 'phone_formatted');

        $this->assertSame($entries[0]->phoneNumber->e164, $item['value']);
        $this->assertSame(
            PhoneFormatter::format($entries[0]->phoneNumber->e164, '+### (##) ###-##-##'),
            $item['display_value']
        );
        $this->assertSame('+### (##) ###-##-##', $item['phone_format']);
    }



    public function test_exhausted_hidden_slot_marked_hidden(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update([
            'key' => 'phone_ro_1', 'scope_type' => 'site', 'scope_id' => $site->id,
        ]);
        app(FailoverEngine::class)->markNumberDown($entries[0]->phoneNumber);

        $payload = app(SitePayloadCompiler::class)->compile($site);
        $item = $this->itemByKey($payload, 'phone_ro_1');

        $this->assertSame('hidden', $item['state']);
        $this->assertNull($item['value']);
    }

    public function test_linked_messenger_keeps_own_url_and_is_independent_from_phone_failover(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update([
            'key' => 'phone_ua_2', 'scope_type' => 'site', 'scope_id' => $site->id,
        ]);
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'viber_ua_2',
            'content' => ['value' => 'Viber support', 'network' => 'viber', 'url' => 'https://viber.example/support', 'linked_slot' => 'phone_ua_2', 'enabled' => true],
        ]);

        $compiler = app(SitePayloadCompiler::class);

        $item = $this->itemByKey($compiler->compile($site), 'viber_ua_2');
        $this->assertSame('Viber support', $item['name']);
        $this->assertSame('ok', $item['state']);
        $this->assertSame('https://viber.example/support', $item['url']);

        app(FailoverEngine::class)->markNumberDown($entries[0]->phoneNumber);

        $item = $this->itemByKey($compiler->compile($site), 'viber_ua_2');
        $this->assertSame('ok', $item['state']);
        $this->assertSame('https://viber.example/support', $item['url']);

        $msg = DataValue::where('key', 'viber_ua_2')->sole();
        $msg->update(['content' => array_merge($msg->content, ['enabled' => false])]);

        $item = $this->itemByKey($compiler->compile($site), 'viber_ua_2');
        $this->assertSame('hidden', $item['state']);
        $this->assertNull($item['value']);
    }

    public function test_independent_messenger_and_world_fallback_geo(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Brand Telegram', 'network' => 'telegram', 'url' => 'https://t.me/brand'],
        ]);

        $item = $this->itemByKey(app(SitePayloadCompiler::class)->compile($site), 'tg_brand');

        $this->assertSame(['WORLD'], $item['geo']);
        $this->assertSame('Brand Telegram', $item['name']);
        $this->assertSame('https://t.me/brand', $item['url']);
    }

    public function test_linked_messenger_respects_pinned_reserve_in_group(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_main',
            'content' => ['value' => 'Main TG', 'network' => 'telegram', 'url' => 'https://t.me/main', 'linked_slot' => 'phone_ua_2', 'enabled' => true],
        ]);
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_backup',
            'content' => ['value' => 'Backup TG', 'network' => 'telegram', 'url' => 'https://t.me/backup', 'linked_slot' => 'phone_ua_2', 'enabled' => true, 'pinned' => true],
        ]);

        $payload = app(SitePayloadCompiler::class)->compile($site);
        $main = $this->itemByKey($payload, 'tg_main');
        $backup = $this->itemByKey($payload, 'tg_backup');

        $this->assertNull($backup);
        $this->assertSame('on_reserve', $main['state']);
        $this->assertTrue($main['is_current']);
        $this->assertSame('Backup TG', $main['value']);
        $this->assertSame('https://t.me/backup', $main['url']);
    }

    public function test_hidden_phone_slot_is_published_with_hidden_state(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update([
            'key' => 'phone_hidden',
            'scope_type' => 'site',
            'scope_id' => $site->id,
            'status' => 'hidden',
        ]);

        $payload = app(SitePayloadCompiler::class)->compile($site);
        $item = $this->itemByKey($payload, 'phone_hidden');

        // Приховані значення публікуються зі state='hidden' (плагін показує «скрыто»
        // в адмінці й ховає від відвідувачів), а не зникають із payload.
        $this->assertNotNull($item);
        $this->assertSame('hidden', $item['state']);
        $this->assertNull($item['value']);
    }

    public function test_hidden_price_is_published_with_hidden_state(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->forSite($site)->ofType('price')->create([
            'key' => 'price_hidden',
            'status' => 'hidden',
            'content' => ['prices' => [['label' => 'UA', 'value' => '1200', 'geo' => ['UA']]]],
        ]);

        $payload = app(SitePayloadCompiler::class)->compile($site);
        $item = $this->itemByKey($payload, 'price_hidden');

        $this->assertNotNull($item);
        $this->assertSame('hidden', $item['state']);
    }

    public function test_linked_slot_is_visual_only(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'viber_orphan',
            'content' => ['value' => 'Viber support', 'network' => 'viber', 'url' => 'https://viber.example/orphan', 'linked_slot' => 'no_such_key', 'enabled' => true],
        ]);

        $item = $this->itemByKey(app(SitePayloadCompiler::class)->compile($site), 'viber_orphan');

        $this->assertSame('ok', $item['state']);
        $this->assertSame('https://viber.example/orphan', $item['url']);
    }

    public function test_linked_whatsapp_builds_wa_me_url(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update([
            'key' => 'phone_wa', 'scope_type' => 'site', 'scope_id' => $site->id,
        ]);
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'wa_main',
            'content' => ['value' => 'WhatsApp support', 'network' => 'whatsapp', 'url' => 'https://wa.me/support', 'linked_slot' => 'phone_wa', 'enabled' => true],
        ]);

        $item = $this->itemByKey(app(SitePayloadCompiler::class)->compile($site), 'wa_main');

        $this->assertSame('https://wa.me/support', $item['url']);
        $this->assertSame('ok', $item['state']);
    }

    public function test_publish_increments_version_per_site(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->forSite($site)->create(['key' => 'note']);

        $compiler = app(SitePayloadCompiler::class);
        $first = $compiler->publish($site);
        $second = $compiler->publish($site);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(2, Publication::where('site_id', $site->id)->count());
        $this->assertSame(2, $second->payload['version']);
    }

    public function test_payload_values_follow_crm_section_order(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->ofType('price')->forSite($site)->create([
            'key' => 'price_ro',
            'content' => ['prices' => [['label' => 'RO', 'value' => '1200', 'geo' => ['RO']]]],
        ]);
        DataValue::factory()->forSite($site)->create(['key' => 'note']);
        DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_support',
            'content' => ['value' => 'Telegram', 'network' => 'telegram', 'url' => 'https://t.me/support'],
        ]);

        [$secondPhone] = $this->slotWithNumbers(['active']);
        $secondPhone->dataValue->update(['key' => 'phone_b', 'scope_type' => 'site', 'scope_id' => $site->id]);

        [$firstPhone] = $this->slotWithNumbers(['active']);
        $firstPhone->dataValue->update(['key' => 'phone_a', 'scope_type' => 'site', 'scope_id' => $site->id]);

        $payload = app(SitePayloadCompiler::class)->compile($site);

        $this->assertSame([
            'phone:phone_a',
            'phone:phone_b',
            'messenger:tg_support',
            'price:price_ro',
            'text:note',
        ], array_map(fn (array $item) => $item['type'].':'.$item['key'], $payload['values']));
    }

    public function test_reserve_messenger_state_and_current_flag(): void
    {
        $site = Site::factory()->create();
        
        $tg = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'tg_brand',
            'content' => ['value' => 'Telegram support', 'network' => 'telegram', 'url' => 'https://t.me/brand', 'linked_slot' => 'phone_ro_1', 'enabled' => true],
        ]);
        
        $viber = DataValue::factory()->ofType('messenger')->forSite($site)->create([
            'key' => 'viber_ro_1',
            'content' => ['value' => 'Viber support', 'network' => 'viber', 'url' => 'https://viber.example', 'messenger_slot' => 'tg_brand', 'enabled' => true],
        ]);

        $compiler = app(SitePayloadCompiler::class);

        $payload = $compiler->compile($site);
        $tgItem = $this->itemByKey($payload, 'tg_brand');
        $viberItem = $this->itemByKey($payload, 'viber_ro_1');

        $this->assertNull($viberItem);
        $this->assertSame('ok', $tgItem['state']);
        $this->assertTrue($tgItem['is_current']);
        $this->assertSame('Telegram support', $tgItem['value']);
        $this->assertSame('https://t.me/brand', $tgItem['url']);
        $this->assertSame('telegram', $tgItem['network']);

        $tg->update(['content' => array_merge($tg->content, ['enabled' => false])]);
        
        $payload = $compiler->compile($site);
        $tgItem = $this->itemByKey($payload, 'tg_brand');
        $viberItem = $this->itemByKey($payload, 'viber_ro_1');

        $this->assertNull($viberItem);
        $this->assertSame('on_reserve', $tgItem['state']);
        $this->assertTrue($tgItem['is_current']);
        $this->assertSame('Viber support', $tgItem['value']);
        $this->assertSame('https://viber.example', $tgItem['url']);
        $this->assertSame('viber', $tgItem['network']);
    }
}

