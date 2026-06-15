<?php

namespace Database\Seeders;

use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Incident;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Models\Publication;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Services\Failover\FailoverEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Демо-дані для адмінки (НЕ викликається з DatabaseSeeder — лише вручну:
 * php artisan db:seed --class=DemoDataSeeder). У local/testing робить
 * demo-reset і створює два сайти в одній групі: RO/UA однакові на обох,
 * RU перекритий на другому.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoDataSeeder: demo-reset дозволений лише в local/testing.');

            return;
        }

        $this->call([
            GeoTagSeeder::class,
            ValueTypeSeeder::class,
        ]);

        DB::transaction(function () {
            $this->wipeDemoData();

            $group = SiteGroup::factory()->create(['name' => 'Brand A']);
            Site::factory()->for($group, 'group')->create([
                'name' => 'Domen RO',
                'domain' => 'domen.ro',
                'country_hint' => 'RO',
            ]);
            $siteUa = Site::factory()->for($group, 'group')->create([
                'name' => 'Domen UA',
                'domain' => 'domen.ua',
                'country_hint' => 'UA',
            ]);

            $world = GeoTag::where('code', 'WORLD')->sole();
            $ua    = GeoTag::where('code', 'UA')->sole();
            $ru    = GeoTag::where('code', 'RU')->sole();
            $by    = GeoTag::where('code', 'BY')->sole();

            // Однаковий RO для всієї групи: WORLD, основний + 1 резерв.
            $this->slot(
                key: 'phone_ro_1',
                scopeType: 'group',
                scopeId: $group->id,
                geoTags: [$world->id],
                numbers: [
                    ['+40211222333', 'RO основний'],
                    ['+40211444555', 'RO резерв'],
                ],
            );

            // Однаковий UA для всієї групи: WORLD + UA, без RU.
            $this->slot(
                key: 'phone_ua_1',
                scopeType: 'group',
                scopeId: $group->id,
                geoTags: [$world->id, $ua->id],
                numbers: [
                    ['+380441112233', 'UA основний'],
                ],
            );

            // Груповий RU діє на сайтах без власного override: WORLD + RU + BY, без UA.
            $this->slot(
                key: 'phone_ru_1',
                scopeType: 'group',
                scopeId: $group->id,
                geoTags: [$world->id, $ru->id, $by->id],
                numbers: [
                    ['+74951234567', 'RU груповий'],
                ],
            );

            // На другому сайті RU відрізняється, але RO і UA лишаються груповими.
            $this->slot(
                key: 'phone_ru_1',
                scopeType: 'site',
                scopeId: $siteUa->id,
                geoTags: [$world->id, $ru->id, $by->id],
                numbers: [
                    ['+74957654321', 'RU для domen.ua'],
                ],
            );
        });

        $this->command?->info('DemoDataSeeder: створено Brand A + domen.ro/domen.ua; RO/UA групові, RU перекритий на domen.ua.');
    }

    /**
     * @param array<int,int> $geoTags
     * @param array<int,array{0:string,1:string}> $numbers
     */
    private function slot(string $key, string $scopeType, int $scopeId, array $geoTags, array $numbers): DataValue
    {
        $value = DataValue::factory()->ofType('phone')->create([
            'key'        => $key,
            'scope_type' => $scopeType,
            'scope_id'   => $scopeId,
            'content'    => null,
            'status'     => 'active',
        ]);

        $slot = PhoneSlot::create([
            'data_value_id' => $value->id,
            'return_mode' => 'auto',
            'exhaustion_policy' => 'hide',
        ]);

        foreach ($numbers as $priority => [$e164, $label]) {
            $phone = PhoneNumber::create([
                'e164' => $e164,
                'label' => $label,
                'status' => 'active',
            ]);

            NumberEntry::create([
                'phone_slot_id'   => $slot->id,
                'priority'        => $priority,
                'phone_number_id' => $phone->id,
            ]);
        }

        $value->geoTags()->sync($geoTags);

        app(FailoverEngine::class)->recompute($slot->fresh());

        return $value->fresh();
    }

    private function wipeDemoData(): void
    {
        Publication::query()->delete();
        ApiToken::query()->delete();
        AuditLog::query()->delete();
        Incident::query()->delete();
        NumberEntry::query()->delete();
        PhoneSlot::query()->delete();
        DataValue::query()->delete();
        PhoneNumber::query()->delete();
        Site::query()->delete();
        SiteGroup::query()->delete();

        $this->command?->info('DemoDataSeeder: попередні робочі дані видалено.');
    }
}
