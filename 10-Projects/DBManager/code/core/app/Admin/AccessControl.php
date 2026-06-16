<?php

namespace App\Admin;

use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use Illuminate\Support\Collection;

class AccessControl
{
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [
        self::ROLE_SUPERADMIN,
        self::ROLE_MANAGER,
        self::ROLE_VIEWER,
    ];

    public function canUseAdmin(?User $user): bool
    {
        return $this->isActiveKnownRole($user);
    }

    public function canManageAccess(?User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function canViewSite(?User $user, Site|int|null $site): bool
    {
        return $this->canForSite($user, $site, 'can_view');
    }

    public function canEditSite(?User $user, Site|int|null $site): bool
    {
        return $this->canForSite($user, $site, 'can_edit');
    }

    public function canPublishSite(?User $user, Site|int|null $site): bool
    {
        return $this->canForSite($user, $site, 'can_publish');
    }

    public function canDeleteSite(?User $user, Site|int|null $site): bool
    {
        return $this->canForSite($user, $site, 'can_delete');
    }

    public function canEditGroup(?User $user, SiteGroup|int|null $group): bool
    {
        return $this->canForGroup($user, $group, 'can_edit');
    }

    public function canPublishGroup(?User $user, SiteGroup|int|null $group): bool
    {
        return $this->canForGroup($user, $group, 'can_publish');
    }

    public function canDeleteGroup(?User $user, SiteGroup|int|null $group): bool
    {
        return $this->canForGroup($user, $group, 'can_delete');
    }

    public function canEditValue(?User $user, DataValue $value): bool
    {
        return $this->canForValue($user, $value, 'can_edit');
    }

    public function canPublishValue(?User $user, DataValue $value): bool
    {
        return $this->canForValue($user, $value, 'can_publish');
    }

    public function canDeleteValue(?User $user, DataValue $value): bool
    {
        return $this->canForValue($user, $value, 'can_delete');
    }

    /** @return Collection<int, Site> */
    public function accessibleSites(?User $user): Collection
    {
        if (! $this->isActiveKnownRole($user)) {
            return collect();
        }

        if ($this->isSuperAdmin($user)) {
            return Site::with('group')->orderBy('id')->get();
        }

        return Site::query()
            ->with('group')
            ->where(function ($query) use ($user): void {
                $query->whereIn('id', $this->siteAccessIds($user))
                    ->orWhereIn('site_group_id', $this->groupAccessIds($user));
            })
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, int> */
    public function accessibleSiteIds(?User $user): array
    {
        return $this->accessibleSites($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function canForValue(?User $user, DataValue $value, string $permission): bool
    {
        if (! $this->isActiveKnownRole($user)) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($user->role !== self::ROLE_MANAGER) {
            return false;
        }

        if ($value->scope_type === 'site') {
            return $this->canForSite($user, (int) $value->scope_id, $permission);
        }

        if ($value->scope_type === 'group') {
            return $this->canForGroup($user, (int) $value->scope_id, $permission);
        }

        return false;
    }

    private function canForSite(?User $user, Site|int|null $site, string $permission): bool
    {
        if (! $this->isActiveKnownRole($user) || ! $site) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $siteModel = $site instanceof Site ? $site : Site::find($site);
        if (! $siteModel) {
            return false;
        }

        $siteAccess = $user->siteAccess()
            ->where('site_id', $siteModel->id)
            ->first();

        if ($siteAccess && $this->permissionGranted($siteAccess, $permission)) {
            return true;
        }

        if ($siteModel->site_group_id) {
            return $this->canForGroup($user, (int) $siteModel->site_group_id, $permission);
        }

        return false;
    }

    private function canForGroup(?User $user, SiteGroup|int|null $group, string $permission): bool
    {
        if (! $this->isActiveKnownRole($user) || ! $group) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $groupId = $group instanceof SiteGroup ? $group->id : $group;
        $access = $user->siteGroupAccess()
            ->where('site_group_id', $groupId)
            ->first();

        return $access ? $this->permissionGranted($access, $permission) : false;
    }

    private function permissionGranted(object $access, string $permission): bool
    {
        if ($permission === 'can_view') {
            return $access->can_view || $access->can_edit || $access->can_delete || $access->can_publish;
        }

        return (bool) $access->{$permission};
    }

    private function isSuperAdmin(?User $user): bool
    {
        return $this->isActiveKnownRole($user) && $user->role === self::ROLE_SUPERADMIN;
    }

    private function isActiveKnownRole(?User $user): bool
    {
        return $user !== null
            && (bool) $user->is_active
            && in_array($user->role, self::ROLES, true);
    }

    /** @return array<int, int> */
    private function siteAccessIds(User $user): array
    {
        return $user->siteAccess()
            ->where(function ($query): void {
                $query->where('can_view', true)
                    ->orWhere('can_edit', true)
                    ->orWhere('can_delete', true)
                    ->orWhere('can_publish', true);
            })
            ->pluck('site_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<int, int> */
    private function groupAccessIds(User $user): array
    {
        return $user->siteGroupAccess()
            ->where(function ($query): void {
                $query->where('can_view', true)
                    ->orWhere('can_edit', true)
                    ->orWhere('can_delete', true)
                    ->orWhere('can_publish', true);
            })
            ->pluck('site_group_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
