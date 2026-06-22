<?php

namespace Tests\Feature\Admin;

use App\Admin\SiteGridReader;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_updates_demo_data_and_preserves_other_sites(): void
    {
        $oldGroup = SiteGroup::factory()->create(['name' => 'Old Brand']);
        Site::factory()->for($oldGroup, 'group')->create(['domain' => 'old.test']);

        $this->seed(DemoDataSeeder::class);

        $this->assertSame(['domen.ro', 'domen.ua', 'old.test'], Site::orderBy('domain')->pluck('domain')->all());
        $this->assertSame(2, SiteGroup::count());
        $this->assertSame(6, DataValue::whereHas('type', fn ($q) => $q->where('code', 'phone'))->count());
        $this->assertSame(4, DataValue::whereHas('type', fn ($q) => $q->where('code', 'messenger'))->count());

        $siteRo = Site::where('domain', 'domen.ro')->sole();
        $siteUa = Site::where('domain', 'domen.ua')->sole();

        $roRows = app(SiteGridReader::class)->forSite($siteRo)['phone'];
        $uaRows = app(SiteGridReader::class)->forSite($siteUa)['phone'];
        $messengerRows = app(SiteGridReader::class)->forSite($siteRo)['messenger'];

        $this->assertCount(3, $roRows);
        $this->assertCount(3, $uaRows);

        $roOnRo = collect($roRows)->firstWhere('key', 'phone_ro_1');
        $uaOnRo = collect($roRows)->firstWhere('key', 'phone_ua_1');
        $ruOnRo = collect($roRows)->firstWhere('key', 'phone_ru_1');
        $roOnUa = collect($uaRows)->firstWhere('key', 'phone_ro_1');
        $uaOnUa = collect($uaRows)->firstWhere('key', 'phone_ua_1');
        $ruOnUa = collect($uaRows)->firstWhere('key', 'phone_ru_1');

        $this->assertSame('+40211222333', $roOnRo['value']);
        $this->assertSame('+40211222333', $roOnUa['value']);
        $this->assertSame(1, $roOnRo['reserves']);
        $this->assertSame(['WORLD'], $roOnRo['geo']);
        $this->assertCount(1, $roOnRo['messengers']);
        $this->assertSame('phone_ro_1', $roOnRo['messengers'][0]['linked_slot']);

        $this->assertSame('+380441112233', $uaOnRo['value']);
        $this->assertSame('+380441112233', $uaOnUa['value']);
        $this->assertSame(['WORLD', 'UA'], $uaOnRo['geo']);

        $this->assertSame('+74951234567', $ruOnRo['value']);
        $this->assertSame('+74957654321', $ruOnUa['value']);
        $this->assertSame('site', $ruOnRo['scope']);
        $this->assertSame('site', $ruOnUa['scope']);
        $this->assertSame(['WORLD', 'RU', 'BY'], $ruOnRo['geo']);
        $this->assertSame(['WORLD', 'RU', 'BY'], $ruOnUa['geo']);

        $tg = collect($messengerRows)->firstWhere('key', 'tg_brand');
        $this->assertNotNull($tg);
        $this->assertSame('https://t.me/brand', $tg['url']);
        $this->assertSame('Написати в Telegram', $tg['value']);
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(['domen.ro', 'domen.ua'], Site::orderBy('domain')->pluck('domain')->all());
        $this->assertSame(6, DataValue::whereHas('type', fn ($q) => $q->where('code', 'phone'))->count());
        $this->assertSame(4, DataValue::whereHas('type', fn ($q) => $q->where('code', 'messenger'))->count());
    }
}
