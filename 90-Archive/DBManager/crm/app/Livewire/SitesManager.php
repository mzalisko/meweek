<?php

namespace App\Livewire;

use App\Admin\AccessControl;
use App\Admin\SiteGridReader;
use App\Admin\SiteHierarchy;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\NumberEntry;
use App\Models\PhoneSlot;
use App\Models\Publication;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Services\Provisioning\SiteProvisioner;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SitesManager extends Component
{
    public bool $showArchived = false;

    public string $siteSearch = '';

    public ?string $panelMode = null;

    public ?int $editingGroupId = null;

    public string $groupName = '';

    public ?int $editingSiteId = null;

    public string $siteName = '';

    public string $siteDomain = '';

    public string $siteCountryHint = '';

    public ?int $siteGroupId = null;

    public ?int $parentSiteId = null;

    public ?string $visibleToken = null;

    public ?int $purgingSiteId = null;

    public string $purgingSiteConfirmation = '';

    public function mount(): void
    {
        $this->authorizeSiteAccess();
    }

    public function startCreateGroup(): void
    {
        $this->authorizeSiteManagement();
        $this->panelMode = 'group';
        $this->editingGroupId = null;
        $this->groupName = '';
        $this->resetValidation();
    }

    public function editGroup(int $id): void
    {
        $this->authorizeSiteManagement();

        $group = SiteGroup::withTrashed()->findOrFail($id);
        $this->panelMode = 'group';
        $this->editingGroupId = $group->id;
        $this->groupName = $group->name;
        $this->resetValidation();
    }

    public function saveGroup(): void
    {
        $this->authorizeSiteManagement();

        $validated = $this->validate([
            'groupName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('site_groups', 'name')->ignore($this->editingGroupId),
            ],
        ]);

        $group = $this->editingGroupId
            ? SiteGroup::withTrashed()->findOrFail($this->editingGroupId)
            : new SiteGroup();

        $old = $group->exists ? $group->only(['name']) : null;
        $group->name = $validated['groupName'];
        $group->save();

        $this->editingGroupId = $group->id;

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => $old ? 'group.updated' : 'group.created',
            'subject_type' => 'SiteGroup',
            'subject_id' => $group->id,
            'old' => $old,
            'new' => $group->only(['name']),
        ]);

        $this->dispatch('toast', message: 'Групу збережено');
    }

    public function archiveGroup(int $id): void
    {
        $this->authorizeSiteManagement();

        $group = SiteGroup::with('sites')->findOrFail($id);

        DB::transaction(function () use ($group) {
            foreach ($group->sites as $site) {
                $site->delete();

                AuditLog::create([
                    'actor_type' => 'user',
                    'actor_id' => auth()->id(),
                    'action' => 'site.archived',
                    'subject_type' => 'Site',
                    'subject_id' => $site->id,
                ]);
            }

            $group->delete();

            AuditLog::create([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'group.archived',
                'subject_type' => 'SiteGroup',
                'subject_id' => $group->id,
            ]);
        });

        if ($this->editingGroupId === $id) {
            $this->closePanel();
        }

        $this->dispatch('toast', message: 'Групу заархівовано разом із сайтами');
    }

    public function restoreGroup(int $id): void
    {
        $this->authorizeSiteManagement();

        $group = SiteGroup::withTrashed()->findOrFail($id);

        DB::transaction(function () use ($group) {
            $group->restore();

            Site::withTrashed()
                ->where('site_group_id', $group->id)
                ->whereNotNull('deleted_at')
                ->restore();

            AuditLog::create([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'group.restored',
                'subject_type' => 'SiteGroup',
                'subject_id' => $group->id,
            ]);
        });

        $this->dispatch('toast', message: 'Групу відновлено');
    }

    public function startCreateSite(?int $groupId = null): void
    {
        $this->authorizeSiteManagement();
        $this->panelMode = 'site';
        $this->editingSiteId = null;
        $this->siteName = '';
        $this->siteDomain = '';
        $this->siteCountryHint = '';
        $this->siteGroupId = $groupId;
        $this->parentSiteId = null;
        $this->visibleToken = null;
        $this->resetValidation();
    }

    public function editSite(int $id): void
    {
        $this->authorizeSiteManagement();

        $site = Site::withTrashed()->findOrFail($id);
        $this->panelMode = 'site';
        $this->editingSiteId = $site->id;
        $this->siteName = $site->name;
        $this->siteDomain = $site->domain;
        $this->siteCountryHint = $site->country_hint ?? '';
        $this->siteGroupId = $site->site_group_id;
        $this->parentSiteId = $site->parent_site_id;
        $this->visibleToken = null;
        $this->resetValidation();
    }

    public function saveSite(): void
    {
        $this->authorizeSiteManagement();

        $validated = $this->validate([
            'siteName' => ['required', 'string', 'max:255'],
            'siteDomain' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sites', 'domain')->ignore($this->editingSiteId),
            ],
            'siteCountryHint' => ['nullable', 'string', 'max:8'],
            'siteGroupId' => ['nullable', 'integer', 'exists:site_groups,id'],
            'parentSiteId' => ['nullable', 'integer', 'exists:sites,id'],
        ]);

        $site = $this->editingSiteId
            ? Site::withTrashed()->findOrFail($this->editingSiteId)
            : new Site();

        $old = $site->exists
            ? $site->only(['name', 'domain', 'country_hint', 'site_group_id'])
            : null;

        $site->name = $validated['siteName'];
        $site->domain = $validated['siteDomain'];
        $site->country_hint = $validated['siteCountryHint'] !== '' ? $validated['siteCountryHint'] : null;
        $site->parent_site_id = $validated['parentSiteId'];
        if ($site->parent_site_id) {
            $parent = Site::withTrashed()->find($site->parent_site_id);
            if ($parent && $site->exists && in_array($parent->id, app(SiteHierarchy::class)->descendantIds($site), true)) {
                $this->addError('parentSiteId', 'Сайт-джерело не може бути сателітом цього сайта.');

                return;
            }
            if ($parent) {
                $site->site_group_id = $parent->site_group_id;
            }
        } else {
            $site->site_group_id = $validated['siteGroupId'];
        }
        $site->save();

        $this->editingSiteId = $site->id;

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => $old ? 'site.updated' : 'site.created',
            'subject_type' => 'Site',
            'subject_id' => $site->id,
            'old' => $old,
            'new' => $site->only(['name', 'domain', 'country_hint', 'site_group_id']),
        ]);

        $this->dispatch('toast', message: 'Сайт збережено');
    }

    public function archiveSite(int $id): void
    {
        $this->authorizeSiteManagement();

        $site = Site::findOrFail($id);
        $site->delete();

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'site.archived',
            'subject_type' => 'Site',
            'subject_id' => $site->id,
        ]);

        if ($this->editingSiteId === $id) {
            $this->closePanel();
        }

        $this->dispatch('toast', message: 'Сайт заархівовано');
    }

    public function restoreSite(int $id): void
    {
        $this->authorizeSiteManagement();

        $site = Site::withTrashed()->findOrFail($id);
        $site->restore();

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'site.restored',
            'subject_type' => 'Site',
            'subject_id' => $site->id,
        ]);

        $this->dispatch('toast', message: 'Сайт відновлено');
    }

    public function confirmPurgeSite(int $id): void
    {
        $this->authorizeSiteManagement();

        $site = Site::withTrashed()->findOrFail($id);

        if (! $site->trashed()) {
            return;
        }

        $this->purgingSiteId = $site->id;
        $this->purgingSiteConfirmation = '';
        $this->resetValidation();
    }

    public function purgeSite(): void
    {
        $this->authorizeSiteManagement();

        if (! $this->purgingSiteId) {
            return;
        }

        $site = Site::withTrashed()->findOrFail($this->purgingSiteId);
        if (! $site->trashed()) {
            $this->closePurgeDialog();

            return;
        }

        $this->validate([
            'purgingSiteConfirmation' => ['required', 'string', 'max:255'],
        ]);

        if (trim($this->purgingSiteConfirmation) !== $site->domain) {
            $this->addError('purgingSiteConfirmation', 'Введіть точну назву домену.');

            return;
        }

        DB::transaction(function () use ($site): void {
            $site->userAccess()->delete();
            $site->tokens()->delete();
            Publication::where('site_id', $site->id)->delete();
            $site->forceDelete();

            AuditLog::create([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'site.purged',
                'subject_type' => 'Site',
                'subject_id' => $site->id,
                'old' => ['domain' => $site->domain],
            ]);
        });

        if ($this->editingSiteId === $site->id) {
            $this->closePanel();
        }

        $this->closePurgeDialog();
        $this->dispatch('toast', message: 'Сайт видалено остаточно');
    }

    public function closePurgeDialog(): void
    {
        $this->purgingSiteId = null;
        $this->purgingSiteConfirmation = '';
        $this->resetValidation('purgingSiteConfirmation');
    }

    public function issueToken(): void
    {
        $this->authorizeSiteManagement();

        $site = Site::findOrFail($this->editingSiteId);
        $connection = app(SiteProvisioner::class)->issuePluginConnection($site);
        $this->visibleToken = $connection['connection_key'];
        $this->auditToken('token.issued', $site->id);
        $this->publishCurrentPayload($site);

        $this->dispatch('toast', message: 'Ключ підключення створено. Скопіюйте зараз — більше не покажемо.');
    }

    public function revokeToken(): void
    {
        $this->authorizeSiteManagement();

        $site = Site::findOrFail($this->editingSiteId);
        app(SiteProvisioner::class)->revokeToken($site);
        $this->visibleToken = null;
        $this->auditToken('token.revoked', $site->id);

        $this->dispatch('toast', message: 'Токени сайта відкликано');
    }

    public function rotateToken(): void
    {
        $this->authorizeSiteManagement();

        $site = Site::findOrFail($this->editingSiteId);
        $provisioner = app(SiteProvisioner::class);
        $provisioner->revokeToken($site);
        $connection = $provisioner->issuePluginConnection($site);
        $this->visibleToken = $connection['connection_key'];
        $this->auditToken('token.rotated', $site->id);
        $this->publishCurrentPayload($site);

        $this->dispatch('toast', message: 'Ключ підключення оновлено. Старий більше не діє.');
    }

    public function hasNoData(): bool
    {
        if (! $this->editingSiteId) {
            return false;
        }

        return DataValue::where('scope_type', 'site')
            ->where('scope_id', $this->editingSiteId)
            ->doesntExist();
    }

    public function cloneParentData(): void
    {
        $this->authorizeSiteManagement();

        if (! $this->editingSiteId || ! $this->parentSiteId) {
            return;
        }

        $site = Site::findOrFail($this->editingSiteId);
        $parent = Site::findOrFail($this->parentSiteId);

        if (! $this->hasNoData()) {
            $this->dispatch('toast', message: 'Сайт вже має власні дані. Клонування скасовано.');

            return;
        }

        $parentValues = DataValue::with(['geoTags', 'phoneSlot.entries'])
            ->where('scope_type', 'site')
            ->where('scope_id', $parent->id)
            ->get();

        if ($parentValues->isEmpty()) {
            $this->dispatch('toast', message: 'На сайті-джерелі немає даних для клонування.');

            return;
        }

        DB::transaction(function () use ($parentValues, $site, $parent) {
            foreach ($parentValues as $pValue) {
                $copyValue = DataValue::create([
                    'key' => $pValue->key,
                    'value_type_id' => $pValue->value_type_id,
                    'scope_type' => 'site',
                    'scope_id' => $site->id,
                    'content' => $pValue->content,
                    'status' => $pValue->status ?? 'active',
                ]);

                $copyValue->geoTags()->sync($pValue->geoTags->pluck('id')->all());

                if ($pValue->phoneSlot) {
                    $sourceSlot = $pValue->phoneSlot;
                    $copySlot = PhoneSlot::create([
                        'data_value_id' => $copyValue->id,
                        'return_mode' => $sourceSlot->return_mode,
                        'exhaustion_policy' => $sourceSlot->exhaustion_policy,
                        'emergency_number' => $sourceSlot->emergency_number,
                    ]);

                    $pinnedPhoneNumberId = null;
                    if ($sourceSlot->pinned_number_entry_id) {
                        $pinnedSource = $sourceSlot->entries->firstWhere('id', $sourceSlot->pinned_number_entry_id);
                        $pinnedPhoneNumberId = $pinnedSource ? (int) $pinnedSource->phone_number_id : null;
                    }

                    $pinnedCopyEntryId = null;
                    foreach ($sourceSlot->entries as $sourceEntry) {
                        $copyEntry = NumberEntry::create([
                            'phone_slot_id' => $copySlot->id,
                            'phone_number_id' => $sourceEntry->phone_number_id,
                            'priority' => $sourceEntry->priority,
                        ]);
                        if ($pinnedPhoneNumberId !== null && (int) $sourceEntry->phone_number_id === $pinnedPhoneNumberId) {
                            $pinnedCopyEntryId = $copyEntry->id;
                        }
                    }

                    if ($pinnedCopyEntryId !== null) {
                        $copySlot->update(['pinned_number_entry_id' => $pinnedCopyEntryId]);
                    }

                    app(\App\Services\Failover\FailoverEngine::class)->recompute($copySlot->fresh(), 'user');
                }
            }

            AuditLog::create([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'site.data_cloned',
                'subject_type' => 'Site',
                'subject_id' => $site->id,
                'new' => ['parent_site_id' => $parent->id],
            ]);
        });

        $publication = app(SitePayloadCompiler::class)->publish($site);
        app(BridgePublisher::class)->push($publication);

        $this->dispatch('toast', message: 'Дані успішно клоновано з сайту-джерела.');
    }

    public function closePanel(): void
    {
        $this->panelMode = null;
        $this->editingGroupId = null;
        $this->groupName = '';
        $this->editingSiteId = null;
        $this->siteName = '';
        $this->siteDomain = '';
        $this->siteCountryHint = '';
        $this->siteGroupId = null;
        $this->parentSiteId = null;
        $this->visibleToken = null;
        $this->closePurgeDialog();
        $this->resetValidation();
    }

    private function auditToken(string $action, int $siteId): void
    {
        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => 'Site',
            'subject_id' => $siteId,
        ]);
    }

    /**
     * @return array{lastSeenAt: ?string, lastVersion: ?int, hasActiveToken: bool, pingUrl: ?string}
     */
    private function connectionStatus(Site $site): array
    {
        return [
            'lastSeenAt' => $site->tokens()->max('last_seen_at'),
            'lastVersion' => Publication::where('site_id', $site->id)->max('version'),
            'hasActiveToken' => $site->tokens()->whereNull('revoked_at')->exists(),
            'pingUrl' => $site->ping_url,
        ];
    }

    private function publishCurrentPayload(Site $site): void
    {
        $publication = app(SitePayloadCompiler::class)->publish($site->fresh());
        app(BridgePublisher::class)->push($publication);
    }

    public function render()
    {
        $this->authorizeSiteAccess();

        $accessControl = app(AccessControl::class);
        $canManageSites = $accessControl->canManageAccess(auth()->user());
        $accessibleSiteIds = $canManageSites
            ? null
            : $accessControl->accessibleSiteIds(auth()->user());

        if (! $canManageSites) {
            $this->showArchived = false;
            $this->panelMode = null;
            $this->editingGroupId = null;
            $this->editingSiteId = null;
            $this->visibleToken = null;
            $this->purgingSiteId = null;
        }

        $groups = SiteGroup::query()
            ->when($this->showArchived, fn ($query) => $query->withTrashed())
            ->with(['sites' => function ($query) {
                $query->whereNull('parent_site_id')
                    ->when($this->showArchived, fn ($q) => $q->withTrashed())
                    ->with(['children' => function ($children) {
                        $children->when($this->showArchived, fn ($q) => $q->withTrashed())
                            ->orderBy('domain');
                    }])
                    ->orderBy('domain');
            }])
            ->orderBy('name')
            ->get();

        $ungroupedSites = Site::query()
            ->whereNull('site_group_id')
            ->whereNull('parent_site_id')
            ->when($this->showArchived, fn ($query) => $query->withTrashed())
            ->with(['children' => function ($query) {
                $query->when($this->showArchived, fn ($q) => $q->withTrashed())
                    ->orderBy('domain');
            }])
            ->orderBy('domain')
            ->get();

        $editingSite = $canManageSites && $this->editingSiteId
            ? Site::withTrashed()->find($this->editingSiteId)
            : null;

        $visibleGroups = $this->filterGroups($this->filterGroupsByAccess($groups, $accessibleSiteIds));
        $visibleUngrouped = $this->filterSitesTree($this->filterSitesTreeByAccess($ungroupedSites, $accessibleSiteIds));

        return view('livewire.sites-manager', [
            'groups' => $visibleGroups,
            'ungroupedSites' => $visibleUngrouped,
            'valueCounts' => $this->valueCounts($visibleGroups, $visibleUngrouped),
            'groupSiteCounts' => $this->groupSiteCounts($visibleGroups),
            'groupOptions' => $canManageSites ? SiteGroup::orderBy('name')->pluck('name', 'id') : collect(),
            'siteOptions' => $canManageSites
                ? Site::query()
                    ->when($this->showArchived, fn ($query) => $query->withTrashed())
                    ->orderBy('domain')
                    ->pluck('domain', 'id')
                : collect(),
            'tokenStatus' => $editingSite ? $this->connectionStatus($editingSite) : null,
            'canManageSites' => $canManageSites,
        ])->layout('components.layouts.admin');
    }

    /**
     * @param array<int, int>|null $siteIds
     */
    private function filterGroupsByAccess(Collection $groups, ?array $siteIds): Collection
    {
        if ($siteIds === null) {
            return $groups;
        }

        return $groups->map(function (SiteGroup $group) use ($siteIds): ?SiteGroup {
            $sites = $this->filterSitesTreeByAccess($group->sites, $siteIds);
            if ($sites->isEmpty()) {
                return null;
            }

            $group->setRelation('sites', $sites);

            return $group;
        })->filter()->values();
    }

    /**
     * @param array<int, int>|null $siteIds
     */
    private function filterSitesTreeByAccess(Collection $sites, ?array $siteIds): Collection
    {
        if ($siteIds === null) {
            return $sites;
        }

        return $sites->flatMap(function (Site $site) use ($siteIds): Collection {
            $children = $site->relationLoaded('children')
                ? $this->filterSitesTreeByAccess($site->children, $siteIds)
                : collect();

            if (in_array((int) $site->id, $siteIds, true)) {
                $site->setRelation('children', $children);

                return collect([$site]);
            }

            return $children;
        })->values();
    }

    private function filterGroups(Collection $groups): Collection
    {
        $search = mb_strtolower(trim($this->siteSearch));
        if ($search === '') {
            return $groups;
        }

        return $groups->map(function (SiteGroup $group) use ($search): ?SiteGroup {
            // Якщо збігається сама назва групи — показуємо всі її сайти (з сателітами),
            // а не лише ті, чий домен збігся з пошуком.
            if (str_contains(mb_strtolower($group->name), $search)) {
                return $group;
            }

            $sites = $this->filterSitesTree($group->sites);
            if ($sites->isEmpty()) {
                return null;
            }

            $group->setRelation('sites', $sites);

            return $group;
        })->filter()->values();
    }

    private function filterSitesTree(Collection $sites): Collection
    {
        $search = mb_strtolower(trim($this->siteSearch));
        if ($search === '') {
            return $sites;
        }

        return $sites->map(function (Site $site) use ($search): ?Site {
            $children = $site->relationLoaded('children')
                ? $this->filterSitesTree($site->children)
                : collect();
            $matches = str_contains(mb_strtolower($site->domain.' '.$site->name), $search);

            if (! $matches && $children->isEmpty()) {
                return null;
            }

            $site->setRelation('children', $children);

            return $site;
        })->filter()->values();
    }

    /**
     * Фактична к-ть значень на кожному показаному сайті: ефективний грід
     * (успадкування з групи й сайтів-джерел мінус приглушені ключі,
     * месенджери — як один слот), той самий, що бачимо в «Керувати даними».
     *
     * @return array<int, int>
     */
    private function valueCounts(Collection $groups, Collection $ungroupedSites): array
    {
        $reader = app(SiteGridReader::class);
        $counts = [];

        foreach ($this->displayedSites($groups, $ungroupedSites) as $site) {
            $counts[$site->id] = collect($reader->forSite($site))
                ->sum(fn (array $rows) => count($rows));
        }

        return $counts;
    }

    /**
     * Усі сайти, що реально відмальовуються в дереві: сайти-джерела та їхні сателіти.
     *
     * @return Collection<int, Site>
     */
    private function displayedSites(Collection $groups, Collection $ungroupedSites): Collection
    {
        $sites = collect();

        $append = function (Site $site) use ($sites): void {
            $sites->push($site);
            foreach ($site->children as $child) {
                $sites->push($child);
            }
        };

        foreach ($groups as $group) {
            foreach ($group->sites as $site) {
                $append($site);
            }
        }

        foreach ($ungroupedSites as $site) {
            $append($site);
        }

        return $sites;
    }

    /**
     * К-ть сайтів у групі з урахуванням сателітів (джерело + сателіти).
     *
     * @return array<int, int>
     */
    private function groupSiteCounts(Collection $groups): array
    {
        $counts = [];

        foreach ($groups as $group) {
            $counts[$group->id] = $group->sites->reduce(
                fn (int $carry, Site $site) => $carry + 1 + $site->children->count(),
                0
            );
        }

        return $counts;
    }

    private function authorizeSiteManagement(): void
    {
        abort_unless(app(AccessControl::class)->canManageAccess(auth()->user()), 403);
    }

    private function authorizeSiteAccess(): void
    {
        abort_unless(app(AccessControl::class)->canUseAdmin(auth()->user()), 403);
    }
}
