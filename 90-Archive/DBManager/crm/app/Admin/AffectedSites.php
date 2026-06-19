<?php

namespace App\Admin;

use App\Models\DataValue;
use App\Models\Site;
use Illuminate\Support\Collection;

class AffectedSites
{
    /** @return Collection<int, Site> сайти, на які впливає значення */
    public function for(DataValue $value): Collection
    {
        return $value->scope_type === 'site'
            ? Site::whereIn('id', app(SiteHierarchy::class)->descendantIds((int) $value->scope_id))->get()
            : Site::where('site_group_id', $value->scope_id)->get();
    }
}
