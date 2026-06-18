<?php

namespace App\Livewire;

use App\Admin\AccessControl;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Services\Audit\AuditRestorer;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;

class AuditManager extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'changes'; // changes | failover | users | systems

    // Фільтри
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'actor')]
    public string $actorFilter = '';

    #[Url(as: 'action')]
    public string $actionFilter = '';

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    // Для детального перегляду історії сайту/групи
    #[Url(as: 'site')]
    public ?int $selectedSiteId = null;

    #[Url(as: 'group')]
    public ?int $selectedGroupId = null;

    public const CHANGE_ACTIONS = [
        'value.created', 'value.updated', 'value.deleted', 'value.geo_changed', 'value.frozen',
        'messenger.added', 'messenger.toggled', 'messenger.pinned', 'messenger.unpinned', 'messenger.removed',
        'messenger.slot_renamed', 'messenger.reserve_added', 'messenger.exhaustion_policy_changed',
        'messenger.return_mode_changed', 'messenger.emergency_changed', 'messenger.geo_changed',
        'messenger.slot_hidden', 'messenger.slot_shown', 'messenger.materialized',
        'slot.removed', 'slot.suppressed', 'slot.renamed', 'slot.hidden', 'slot.shown',
        'phone.materialized', 'phone.override_collapsed',
        'number.added', 'number.removed', 'number.reordered', 'number.status_changed', 'number.edited',
        'audit.restored'
    ];

    public const FAILOVER_ACTIONS = [
        'number.down', 'number.recovered', 'failover.switch',
    ];

    public const USER_ACTIONS = [
        'user.login', 'user.logout', 'user.login_failed',
        'user.created', 'user.updated', 'user.deleted', 'user.activated', 'user.deactivated', 'user.password_reset', 'user.sessions_revoked',
    ];

    public const SYSTEM_ACTIONS = [
        'group.created', 'group.updated', 'group.archived', 'group.restored',
        'site.created', 'site.updated', 'site.archived', 'site.restored', 'site.purged',
        'site.token_issued', 'site.token_revoked', 'site.token_rotated',
        'slot.pinned', 'slot.unpinned',
        'webhook.unknown_number'
    ];

    public function mount(): void
    {
        $ac = app(AccessControl::class);
        if (! $ac->canUseAdmin(auth()->user())) {
            abort(403, 'Доступ заборонено.');
        }
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->resetFilters();
    }

    public function selectSite(?int $siteId): void
    {
        $this->selectedSiteId = $siteId;
        $this->selectedGroupId = null;
        $this->resetPage();
    }

    public function selectGroup(?int $groupId): void
    {
        $this->selectedGroupId = $groupId;
        $this->selectedSiteId = null;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->actorFilter = '';
        $this->actionFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
    }

    public function restore(int $logId): void
    {
        $log = AuditLog::findOrFail($logId);
        $user = auth()->user();

        if (AuditRestorer::restore($log, $user)) {
            $this->dispatch('toast', message: 'Дані успішно відновлено та опубліковано!');
        } else {
            $this->addError('restore', 'Помилка відновлення. Немає прав або дія не підтримує відкат.');
            $this->dispatch('toast', message: 'Помилка відновлення.');
        }
    }

    public function render()
    {
        $ac = app(AccessControl::class);
        $user = auth()->user();

        $auditAccess = [
            'changes' => $ac->canViewHistory($user),
            'failover' => $ac->canViewFailover($user),
            'users' => $ac->canViewUserLogs($user),
            'systems' => $ac->canViewSystemLogs($user),
        ];

        if (! in_array(true, $auditAccess, true)) {
            abort(403, 'Доступ до аудиту заборонено.');
        }

        if (! ($auditAccess[$this->activeTab] ?? false)) {
            $this->activeTab = array_key_first(array_filter($auditAccess));
        }

        // 1. Отримуємо список сайтів, історію яких можна бачити
        $allSites = Site::all()->filter(fn ($s) => $ac->canViewHistory($user, $s));
        $allGroups = SiteGroup::all()->filter(fn ($g) => $ac->canEditGroup($user, $g));

        if ($this->activeTab === 'changes') {
            // Перевіряємо, чи ми у детальному перегляді
            if ($this->selectedSiteId || $this->selectedGroupId) {
                $logs = $this->queryDetailedLogs();
                return view('livewire.audit-manager', [
                    'logs' => $logs,
                    'allSites' => $allSites,
                    'allGroups' => $allGroups,
                    'auditAccess' => $auditAccess,
                    'siteModel' => $this->selectedSiteId ? Site::find($this->selectedSiteId) : null,
                    'groupModel' => $this->selectedGroupId ? SiteGroup::find($this->selectedGroupId) : null,
                ])->layout('components.layouts.admin');
            }

            // Зведений перегляд (General View)
            $summary = $this->buildSummaryData($allSites, $allGroups);
            return view('livewire.audit-manager', [
                'summary' => $summary,
                'allSites' => $allSites,
                'allGroups' => $allGroups,
                'auditAccess' => $auditAccess,
            ])->layout('components.layouts.admin');
        }

        if ($this->activeTab === 'failover') {
            $logs = $this->queryFailoverLogs();
            return view('livewire.audit-manager', [
                'logs' => $logs,
                'allSites' => $allSites,
                'allGroups' => $allGroups,
                'auditAccess' => $auditAccess,
            ])->layout('components.layouts.admin');
        }

        if ($this->activeTab === 'users') {
            $logs = $this->queryUserLogs();
            return view('livewire.audit-manager', [
                'logs' => $logs,
                'allSites' => $allSites,
                'allGroups' => $allGroups,
                'auditAccess' => $auditAccess,
            ])->layout('components.layouts.admin');
        }

        // Системні логи (System Logs)
        $logs = $this->querySystemLogs();
        return view('livewire.audit-manager', [
            'logs' => $logs,
            'allSites' => $allSites,
            'allGroups' => $allGroups,
            'auditAccess' => $auditAccess,
        ])->layout('components.layouts.admin');
    }

    /**
     * Будує зведену статистику по сайтах та групах для вкладки змін.
     */
    private function buildSummaryData($sites, $groups): array
    {
        // Отримуємо останні 1000 логів змін для швидкої агрегації в пам'яті
        $recentLogs = AuditLog::whereIn('action', self::CHANGE_ACTIONS)
            ->orderBy('created_at', 'desc')
            ->limit(1000)
            ->get();

        $siteSummary = [];

        // Ініціалізуємо структури для всіх дозволених
        foreach ($sites as $site) {
            $siteSummary[$site->id] = [
                'type' => 'site',
                'id' => $site->id,
                'name' => $site->domain,
                'count' => 0,
                'last_changed' => null,
            ];
        }

        // Агрегуємо логи
        foreach ($recentLogs as $log) {
            $scopeType = null;
            $scopeId = null;

            // Зчитуємо з DataValue
            if ($log->subject_type === 'DataValue') {
                $dv = DataValue::find($log->subject_id);
                if ($dv) {
                    $scopeType = $dv->scope_type;
                    $scopeId = $dv->scope_id;
                }
            }

            // Або з JSON payload
            if (! $scopeType && is_array($log->old)) {
                $scopeType = $log->old['scope_type'] ?? null;
                $scopeId = $log->old['scope_id'] ?? null;
            }
            if (! $scopeType && is_array($log->new)) {
                $scopeType = $log->new['scope_type'] ?? null;
                $scopeId = $log->new['scope_id'] ?? null;
            }

            if ($scopeType === 'site' && isset($siteSummary[$scopeId])) {
                $siteSummary[$scopeId]['count']++;
                if (! $siteSummary[$scopeId]['last_changed']) {
                    $siteSummary[$scopeId]['last_changed'] = $log->created_at;
                }
            } elseif ($scopeType === 'group') {
                // Агрегуємо зміни групи на всі дозволені сайти цієї групи
                foreach ($sites as $site) {
                    if ($site->site_group_id === (int) $scopeId && isset($siteSummary[$site->id])) {
                        $siteSummary[$site->id]['count']++;
                        if (! $siteSummary[$site->id]['last_changed']) {
                            $siteSummary[$site->id]['last_changed'] = $log->created_at;
                        }
                    }
                }
            }
        }

        // Фільтруємо лише ті, які мають зміни, та сортуємо за останньою зміною
        $merged = array_filter($siteSummary, fn ($item) => $item['count'] > 0);

        usort($merged, function ($a, $b) {
            if (! $a['last_changed']) return 1;
            if (! $b['last_changed']) return -1;
            return $b['last_changed']->timestamp <=> $a['last_changed']->timestamp;
        });

        return $merged;
    }

    /**
     * Запит детальних логів для обраного сайту чи групи.
     */
    private function queryDetailedLogs()
    {
        $query = AuditLog::whereIn('action', self::CHANGE_ACTIONS);

        if ($this->selectedSiteId) {
            $site = Site::find($this->selectedSiteId);
            $groupId = $site?->site_group_id;

            if (! $site || ! app(AccessControl::class)->canViewHistory(auth()->user(), $site)) {
                $query->whereRaw('1 = 0');
                $this->applyFilters($query);

                return $query->orderBy('created_at', 'desc')->paginate(20);
            }

            // Збираємо існуючі DataValue цього сайту чи групи
            $existingIds = DataValue::where(function ($q) use ($site, $groupId) {
                $q->where(function ($q2) use ($site) {
                    $q2->where('scope_type', 'site')->where('scope_id', $site->id);
                });
                if ($groupId) {
                    $q->orWhere(function ($q2) use ($groupId) {
                        $q2->where('scope_type', 'group')->where('scope_id', $groupId);
                    });
                }
            })->pluck('id')->all();

            $query->where(function ($q) use ($existingIds, $site, $groupId) {
                $q->whereIn('subject_id', $existingIds);
                $q->orWhere(function ($q2) use ($site) {
                    $q2->where('old->scope_type', 'site')->where('old->scope_id', $site->id);
                })->orWhere(function ($q2) use ($site) {
                    $q2->where('new->scope_type', 'site')->where('new->scope_id', $site->id);
                });
                if ($groupId) {
                    $q->orWhere(function ($q2) use ($groupId) {
                        $q2->where('old->scope_type', 'group')->where('old->scope_id', $groupId);
                    })->orWhere(function ($q2) use ($groupId) {
                        $q2->where('new->scope_type', 'group')->where('new->scope_id', $groupId);
                    });
                }
            });
        } elseif ($this->selectedGroupId) {
            if (! app(AccessControl::class)->canViewGroupHistory(auth()->user(), $this->selectedGroupId)) {
                $query->whereRaw('1 = 0');
                $this->applyFilters($query);

                return $query->orderBy('created_at', 'desc')->paginate(20);
            }

            $existingIds = DataValue::where('scope_type', 'group')
                ->where('scope_id', $this->selectedGroupId)
                ->pluck('id')
                ->all();

            $query->where(function ($q) use ($existingIds) {
                $q->whereIn('subject_id', $existingIds)
                    ->orWhere('old->scope_type', 'group')->where('old->scope_id', $this->selectedGroupId)
                    ->orWhere('new->scope_type', 'group')->where('new->scope_id', $this->selectedGroupId);
            });
        }

        $this->applyFilters($query);

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * Запит failover-подій телефонних ліній.
     */
    private function queryFailoverLogs()
    {
        $query = AuditLog::whereIn('action', self::FAILOVER_ACTIONS);
        $ac = app(AccessControl::class);
        $user = auth()->user();

        if (! $ac->canManageAccess($user)) {
            $siteIds = Site::all()
                ->filter(fn (Site $site) => $ac->canViewFailover($user, $site))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($siteIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($siteIds): void {
                    foreach ($siteIds as $siteId) {
                        $q->orWhereJsonContains('old->site_ids', $siteId)
                            ->orWhereJsonContains('new->site_ids', $siteId);
                    }
                });
            }
        }

        $this->applyFilters($query);

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * Запит подій користувачів, сесій і доступів.
     */
    private function queryUserLogs()
    {
        $query = AuditLog::whereIn('action', self::USER_ACTIONS);

        if (! app(AccessControl::class)->canViewUserLogs(auth()->user())) {
            $query->whereRaw('1 = 0');
        }

        $this->applyFilters($query);

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * Запит системних логів подій.
     */
    private function querySystemLogs()
    {
        $query = AuditLog::whereIn('action', self::SYSTEM_ACTIONS);

        if (! app(AccessControl::class)->canViewSystemLogs(auth()->user())) {
            $query->whereRaw('1 = 0');
        }

        $this->applyFilters($query);

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * Застосовує загальні фільтри пошуку до запиту.
     */
    private function applyFilters($query): void
    {
        if ($this->search !== '') {
            $searchVal = '%' . $this->search . '%';
            $query->where(function ($q) use ($searchVal) {
                $q->where('action', 'like', $searchVal)
                    ->orWhere('subject_type', 'like', $searchVal)
                    ->orWhere('old', 'like', $searchVal)
                    ->orWhere('new', 'like', $searchVal);
            });
        }

        if ($this->actorFilter !== '') {
            if ($this->actorFilter === 'system' || $this->actorFilter === 'webhook') {
                $query->where('actor_type', $this->actorFilter);
            } else {
                $query->where('actor_type', 'user')
                    ->where('actor_id', (int) $this->actorFilter);
            }
        }

        if ($this->actionFilter !== '') {
            $query->where('action', $this->actionFilter);
        }

        if ($this->dateFrom !== '') {
            $query->where('created_at', '>=', $this->dateFrom . ' 00:00:00');
        }

        if ($this->dateTo !== '') {
            $query->where('created_at', '<=', $this->dateTo . ' 23:59:59');
        }
    }
}
