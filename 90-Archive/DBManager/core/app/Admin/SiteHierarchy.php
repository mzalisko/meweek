<?php

namespace App\Admin;

use App\Models\Site;
use Illuminate\Support\Collection;

class SiteHierarchy
{
    /**
     * @return Collection<int, Site>
     */
    public function chain(Site $site): Collection
    {
        $chain = collect();
        $current = Site::with('parentSite')->find($site->id);

        while ($current) {
            $chain->prepend($current);
            $current = $current->parentSite ? Site::with('parentSite')->find($current->parentSite->id) : null;
        }

        return $chain;
    }

    /**
     * @return array<int, int>
     */
    public function ancestorIds(Site|int $site): array
    {
        $current = $site instanceof Site
            ? Site::with('parentSite')->find($site->id)
            : Site::with('parentSite')->find($site);
        $ids = [];
        $visited = [];

        while ($current?->parentSite && ! in_array($current->parentSite->id, $visited, true)) {
            $parent = $current->parentSite;
            $ids[] = (int) $parent->id;
            $visited[] = (int) $parent->id;
            $current = Site::with('parentSite')->find($parent->id);
        }

        return $ids;
    }

    /**
     * @return array<int, int>
     */
    public function descendantIds(Site|int $site): array
    {
        $rootId = $site instanceof Site ? (int) $site->id : (int) $site;
        $ids = [$rootId];
        $frontier = [$rootId];

        while ($frontier !== []) {
            $children = Site::query()
                ->whereIn('parent_site_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $newIds = array_values(array_diff($children, $ids));
            if ($newIds === []) {
                break;
            }

            $ids = array_values(array_unique(array_merge($ids, $newIds)));
            $frontier = $newIds;
        }

        return $ids;
    }
}
