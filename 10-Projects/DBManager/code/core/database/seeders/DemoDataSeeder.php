<?php

namespace Database\Seeders;

use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Services\Failover\FailoverEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Демо-дані для адмінки (НЕ викликається з DatabaseSeeder — лише вручну:
 * php artisan db:seed --class=DemoDataSeeder). Видаляє попередні demo-дані
 * й наповнює грід реалістичним набором з трьома телефонними слотами:
 * RO (world + резерв), UA (world+UA), RU (world+RU+BY).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->wipeDemoData();

        $group  = SiteGroup::factory()->create(['name' => 'Brand A']);
        $siteRo = Site::factory()->for($group, 'group')->create(['domain' => 'domen.ro']);
        Site::factory()->for($group, 'group')->create(['domain' => 'domen.ua']);

        $world = GeoTag::firstOrCreate(['code' => 'WORLD'], ['name' => 'World (catch-all)']);
        $ua    = GeoTag::firstOrCreate(['code' => 'UA'],    ['name' => 'Ukraine']);
        $ru    = GeoTag::firstOrCreate(['code' => 'RU'],    ['name' => 'Russia']);
        $by    = GeoTag::firstOrCreate(['code' => 'BY'],    ['name' => 'Belarus']);

        // RO phone slot — group scope, geo: WORLD, 1 main + 1 reserve
        $roValue = $this->slot(
            statuses: ['active', 'active'],
            key: 'phone_ro_1',
            scopeType: 'group',
            scopeId: $group->id,
            geoTags: [$world->id],
        );

        // UA phone slot — site scope, geo: WORLD + UA
        $this->slot(
            statuses: ['active'],
            key: 'phone_ua_1',
            scopeType: 'site',
            scopeId: $siteRo->id,
            geoTags: [$world->id, $ua->id],
        );

        // RU phone slot — group scope, geo: WORLD + RU + BY
        $this->slot(
            statuses: ['active'],
            key: 'phone_ru_1',
            scopeType: 'group',
            scopeId: $group->id,
            geoTags: [$world->id, $ru->id, $by->id],
        );

        // A messenger without linked_slot so it appears in "available to link"
        DataValue::factory()->forGroup($group)->ofType('messenger')->create([
            'key'     => 'tg_brand',
            'content' => ['network' => 'telegram', 'url' => 'https://t.me/brand'],
        ]);

        $this->command?->info('DemoDataSeeder: Brand A + domen.ro/domen.ua, 3 phone slots (RO/UA/RU).');
    }

    /** @param array<int,string> $statuses priority => active|down */
    private function slot(array $statuses, string $key, string $scopeType, int $scopeId, array $geoTags = []): DataValue
    {
        $slot = PhoneSlot::factory()->create();

        foreach ($statuses as $priority => $status) {
            NumberEntry::factory()->for($slot, 'slot')->create([
                'priority'        => $priority,
                'phone_number_id' => PhoneNumber::factory()->create(['status' => $status])->id,
            ]);
        }

        $slot->dataValue->update(['key' => $key, 'scope_type' => $scopeType, 'scope_id' => $scopeId]);

        if ($geoTags) {
            $slot->dataValue->geoTags()->sync($geoTags);
        }

        app(FailoverEngine::class)->recompute($slot->fresh());

        return $slot->dataValue->fresh();
    }

    private function wipeDemoData(): void
    {
        $demoGroupNames = ['Brand A'];
        $groups = SiteGroup::whereIn('name', $demoGroupNames)->with('sites')->get();

        foreach ($groups as $group) {
            $siteIds = $group->sites->pluck('id');

            // Delete DataValues scoped to sites in this group
            DataValue::where('scope_type', 'site')
                ->whereIn('scope_id', $siteIds)
                ->with(['phoneSlot.entries.phoneNumber', 'geoTags'])
                ->get()
                ->each(fn ($dv) => $this->deleteDataValue($dv));

            // Delete DataValues scoped to the group itself
            DataValue::where('scope_type', 'group')
                ->where('scope_id', $group->id)
                ->with(['phoneSlot.entries.phoneNumber', 'geoTags'])
                ->get()
                ->each(fn ($dv) => $this->deleteDataValue($dv));

            $group->sites()->delete();
            $group->delete();
        }

        $this->command?->info('DemoDataSeeder: попередні demo-дані видалено.');
    }

    private function deleteDataValue(DataValue $dv): void
    {
        $dv->geoTags()->detach();

        if ($dv->relationLoaded('phoneSlot') && $dv->phoneSlot) {
            foreach ($dv->phoneSlot->entries as $entry) {
                $pn = $entry->phoneNumber;
                $entry->delete();
                $pn?->delete();
            }
            $dv->phoneSlot->delete();
        }

        $dv->delete();
    }
}
