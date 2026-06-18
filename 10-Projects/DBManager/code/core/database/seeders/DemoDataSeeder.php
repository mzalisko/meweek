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
use App\Models\ValueType;
use App\Services\Failover\FailoverEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Демо-дані для адмінки (НЕ викликається з DatabaseSeeder — лише вручну:
 * php artisan db:seed --class=DemoDataSeeder). У local/testing оновлює
 * лише власні demo-дані (Brand A + domen.ro/domen.ua) і створює два сайти:
 * RO/UA однакові на обох, RU перекритий на другому.
 */
class DemoDataSeeder extends Seeder
{
    private const DEMO_GROUP = 'Brand A';

    private const DEMO_DOMAINS = ['domen.ro', 'domen.ua'];

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

            $group = SiteGroup::factory()->create(['name' => self::DEMO_GROUP]);
            $siteRo = Site::factory()->for($group, 'group')->create([
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

            // phone_ro_1 — основний номер RO для обох сайтів (тепер локально на кожному сайті)
            foreach ([$siteRo, $siteUa] as $site) {
                $this->slot(
                    key: 'phone_ro_1',
                    scopeType: 'site',
                    scopeId: $site->id,
                    geoTags: [$world->id],
                    numbers: [
                        ['+40211222333', 'RO основний'],
                        ['+40211222444', 'RO резерв'],
                    ],
                );
            }

            // phone_ua_1 — номер для України: WORLD + UA (тепер локально на кожному сайті)
            foreach ([$siteRo, $siteUa] as $site) {
                $this->slot(
                    key: 'phone_ua_1',
                    scopeType: 'site',
                    scopeId: $site->id,
                    geoTags: [$world->id, $ua->id],
                    numbers: [
                        ['+380441112233', 'UA основний'],
                    ],
                );
            }

            // phone_ru_1 — локальний номер для RU/BY на domen.ro
            $this->slot(
                key: 'phone_ru_1',
                scopeType: 'site',
                scopeId: $siteRo->id,
                geoTags: [$world->id, $ru->id, $by->id],
                numbers: [
                    ['+74951234567', 'RU для domen.ro'],
                ],
            );

            // phone_ru_1 — site-override на domen.ua: інший номер, та сама гео.
            $this->slot(
                key: 'phone_ru_1',
                scopeType: 'site',
                scopeId: $siteUa->id,
                geoTags: [$world->id, $ru->id, $by->id],
                numbers: [
                    ['+74957654321', 'RU для domen.ua'],
                ],
            );

            $messengerType = ValueType::where('code', 'messenger')->sole();

            foreach ([$siteRo, $siteUa] as $site) {
                // tg_brand — локальний месенджер, прив'язаний до phone_ro_1
                DataValue::create([
                    'key' => 'tg_brand',
                    'value_type_id' => $messengerType->id,
                    'scope_type' => 'site',
                    'scope_id' => $site->id,
                    'content' => [
                        'value' => 'Написати в Telegram',
                        'network' => 'telegram',
                        'url' => 'https://t.me/brand',
                        'linked_slot' => 'phone_ro_1',
                        'exhaustion_policy' => 'hide',
                    ],
                    'status' => 'active',
                ])->geoTags()->sync([$world->id]);

                // viber_ro_1 — резерв месенджера tg_brand
                DataValue::create([
                    'key' => 'viber_ro_1',
                    'value_type_id' => $messengerType->id,
                    'scope_type' => 'site',
                    'scope_id' => $site->id,
                    'content' => [
                        'value' => 'Viber підтримка',
                        'network' => 'viber',
                        'url' => 'viber://chat?number=%2B40211222333',
                        'messenger_slot' => 'tg_brand',
                        'exhaustion_policy' => 'hide',
                    ],
                    'status' => 'active',
                ])->geoTags()->sync([$world->id]);
            }
        });

        $this->command?->info('DemoDataSeeder: створено Brand A + domen.ro/domen.ua; RO/UA групові, RU перекритий на domen.ua.');
    }

    /**
     * @param array<int,int> $geoTags
     * @param array<int,array{0:string,1:string}> $numbers
     */
    private function slot(string $key, string $scopeType, int $scopeId, array $geoTags, array $numbers): DataValue
    {
        $existing = DataValue::query()
            ->where('key', $key)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->first();

        if ($existing) {
            $existing->forceDelete();
        }

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
            $phone = PhoneNumber::updateOrCreate(
                ['e164' => $e164],
                ['label' => $label, 'status' => 'active', 'down_since' => null],
            );

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
        $groups = SiteGroup::withTrashed()
            ->where('name', self::DEMO_GROUP)
            ->with(['sites' => fn ($query) => $query->withTrashed()])
            ->get();
        $sites = Site::withTrashed()->whereIn('domain', self::DEMO_DOMAINS)->get();

        $groupIds = $groups->pluck('id')
            ->merge($sites->pluck('site_group_id')->filter())
            ->unique()
            ->values();
        $siteIds = $groups->flatMap->sites->pluck('id')
            ->merge($sites->pluck('id'))
            ->unique()
            ->values();

        DataValue::where(function ($query) use ($groupIds, $siteIds) {
            $query->where(fn ($q) => $q->where('scope_type', 'group')->whereIn('scope_id', $groupIds))
                ->orWhere(fn ($q) => $q->where('scope_type', 'site')->whereIn('scope_id', $siteIds));
        })
            ->with(['phoneSlot.entries.phoneNumber', 'geoTags'])
            ->get()
            ->each(fn (DataValue $value) => $this->deleteDataValue($value));

        Publication::whereIn('site_id', $siteIds)->delete();
        ApiToken::whereIn('site_id', $siteIds)->delete();

        Site::whereIn('id', $siteIds)->forceDelete();
        SiteGroup::whereIn('id', $groupIds)
            ->whereDoesntHave('sites')
            ->forceDelete();

        $this->command?->info('DemoDataSeeder: попередні demo-дані оновлено без очищення сторонніх записів.');
    }

    private function deleteDataValue(DataValue $value): void
    {
        $value->geoTags()->detach();

        if ($value->phoneSlot) {
            foreach ($value->phoneSlot->entries as $entry) {
                $phoneNumber = $entry->phoneNumber;
                $entry->delete();

                if ($phoneNumber && ! NumberEntry::where('phone_number_id', $phoneNumber->id)->exists()) {
                    $phoneNumber->delete();
                }
            }

            Incident::where('subject_type', 'phone_slot')->where('subject_id', $value->phoneSlot->id)->delete();
            AuditLog::where('subject_type', 'phone_slot')->where('subject_id', $value->phoneSlot->id)->delete();
            $value->phoneSlot->delete();
        }

        AuditLog::where('subject_type', 'DataValue')->where('subject_id', $value->id)->delete();
        $value->delete();
    }
}
