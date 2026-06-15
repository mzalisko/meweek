<?php

namespace Database\Seeders;

use App\Models\DataValue;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Services\Failover\FailoverEngine;
use Illuminate\Database\Seeder;

/**
 * Демо-дані для адмінки (НЕ викликається з DatabaseSeeder — лише вручну:
 * php artisan db:seed --class=DemoDataSeeder). Наповнює грід «Значення»
 * реалістичним набором, щоб побачити failover-статуси й області дії.
 * Ідемпотентний: якщо demo-сайт уже є — нічого не робить.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Site::where('domain', 'domen.ua')->exists()) {
            $this->command?->warn('DemoDataSeeder: demo-дані вже є — пропущено.');

            return;
        }

        $group = SiteGroup::factory()->create(['name' => 'Brand A']);
        $siteUa = Site::factory()->for($group, 'group')->create(['domain' => 'domen.ua']);
        Site::factory()->for($group, 'group')->create(['domain' => 'domen.org']);

        // Телефонні слоти з ланцюгами номерів → різні статуси failover.
        $this->slot(['active', 'active'], 'phone_ua_1', 'group', $group->id);   // ok, 1 резерв
        $this->slot(['down', 'active'], 'phone_ua_2', 'site', $siteUa->id);     // на резерві, ✎ сайта
        $this->slot(['down', 'down'], 'phone_ro_1', 'group', $group->id);       // вичерпано

        // Не-телефонні значення.
        DataValue::factory()->forGroup($group)->ofType('price')->create([
            'key' => 'price_basic', 'content' => ['value' => '1200', 'currency' => 'UAH'],
        ]);
        DataValue::factory()->forSite($siteUa)->create([
            'key' => 'address_main', 'content' => ['value' => 'вул. Хрещатик, 1'],
        ]);
        DataValue::factory()->forGroup($group)->ofType('messenger')->create([
            'key' => 'tg_brand', 'content' => ['network' => 'telegram', 'url' => 'https://t.me/brand'],
        ]);

        $this->command?->info('DemoDataSeeder: створено групу Brand A + domen.ua/domen.org із демо-значеннями.');
    }

    /** @param array<int,string> $statuses priority => active|down */
    private function slot(array $statuses, string $key, string $scopeType, int $scopeId): void
    {
        $slot = PhoneSlot::factory()->create();
        foreach ($statuses as $priority => $status) {
            NumberEntry::factory()->for($slot, 'slot')->create([
                'priority' => $priority,
                'phone_number_id' => PhoneNumber::factory()->create(['status' => $status])->id,
            ]);
        }
        $slot->dataValue->update(['key' => $key, 'scope_type' => $scopeType, 'scope_id' => $scopeId]);
        app(FailoverEngine::class)->recompute($slot->fresh());
    }
}
