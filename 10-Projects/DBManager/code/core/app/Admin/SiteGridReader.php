<?php

namespace App\Admin;

use App\Models\DataValue;
use App\Models\Site;
use App\Services\Failover\SlotResolver;
use Illuminate\Support\Collection;

class SiteGridReader
{
    public function __construct(private SlotResolver $resolver) {}

    /**
     * Повертає рядки гріда для сайта, згруповані за типом.
     *
     * @return array<string, array<int, array>> тип → рядки
     */
    public function forSite(Site $site): array
    {
        $with = ['type', 'geoTags', 'phoneSlot.entries.phoneNumber'];

        $group = $site->site_group_id
            ? DataValue::with($with)
                ->where('status', 'active')
                ->where('scope_type', 'group')
                ->where('scope_id', $site->site_group_id)
                ->get()
            : collect();

        $own = DataValue::with($with)
            ->where('status', 'active')
            ->where('scope_type', 'site')
            ->where('scope_id', $site->id)
            ->get();

        $ownKeys = $own->pluck('key')->flip();

        // Власні значення сайта перекривають групові (те саме, що SitePayloadCompiler::effectiveValues)
        /** @var Collection<string, DataValue> $effective */
        $effective = $group->toBase()->keyBy('key')->merge($own->toBase()->keyBy('key'));

        $rows = [];
        foreach ($effective as $value) {
            $scope = $ownKeys->has($value->key) ? 'site' : 'group';
            $row = $this->buildRow($value, $scope);
            $rows[$value->type->code][] = $row;
        }

        return $rows;
    }

    private function buildRow(DataValue $value, string $scope): array
    {
        $geo = $value->geoTags->pluck('code')->all() ?: ['WORLD'];

        $base = [
            'key'      => $value->key,
            'type'     => $value->type->code,
            'geo'      => $geo,
            'scope'    => $scope,
            'reserves' => 0,
            'state'    => 'ok',
            'value'    => $value->content['value'] ?? null,
        ];

        $slot = $value->phoneSlot;
        if ($slot) {
            $resolved = $this->resolver->resolve($slot);

            // Резерви = кількість записів після поточного (entries - 1)
            $base['reserves'] = max(0, $slot->entries->count() - 1);
            $base['state']    = $resolved->visible ? $resolved->state : 'hidden';
            $base['value']    = $resolved->visible ? $resolved->number : null;
        }

        return $base;
    }
}
