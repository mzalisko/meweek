<?php

namespace Tests\Feature;

use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Publication;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Services\Failover\FailoverEngine;
use App\Services\Publishing\SitePayloadCompiler;
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

        $this->assertSame('on_reserve', $main['state']);
        $this->assertSame('ok', $backup['state']);
        $this->assertTrue($backup['is_current']);
    }

    public function test_hidden_phone_slot_is_omitted_from_payload(): void
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

        $this->assertNull($this->itemByKey($payload, 'phone_hidden'));
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
}
