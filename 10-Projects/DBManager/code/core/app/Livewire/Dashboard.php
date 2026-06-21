<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\UserFavorite;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Dashboard extends Component
{
    use WithPagination;

    public string $search = '';

    public function toggleFavorite(string $type, int $id): void
    {
        $userId = auth()->id();
        $favorableType = $type === 'group' ? SiteGroup::class : Site::class;

        $favorite = UserFavorite::where('user_id', $userId)
            ->where('favorable_type', $favorableType)
            ->where('favorable_id', $id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            $this->dispatch('toast', message: 'Вилучено з улюблених');
        } else {
            UserFavorite::create([
                'user_id' => $userId,
                'favorable_type' => $favorableType,
                'favorable_id' => $id,
            ]);
            $this->dispatch('toast', message: 'Додано до улюблених');
        }
    }

    public function acknowledgeIncident(int $id): void
    {
        $incident = Incident::where('status', 'new')->find($id);
        if ($incident) {
            $incident->update([
                'status' => 'acknowledged',
                'acknowledged_by' => auth()->id(),
                'acknowledged_at' => now(),
            ]);

            AuditLog::create([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'incident.acknowledge',
                'subject_type' => 'Incident',
                'subject_id' => $incident->id,
                'new' => ['message' => $incident->message],
            ]);

            $this->dispatch('toast', message: 'Інцидент підтверджено');
        }
    }

    private function getSiteStatus(Site $site): array
    {
        $tokens = $site->tokens()->whereNull('revoked_at')->get();
        $lastSeen = $tokens->max('last_seen_at');
        $hasActiveToken = $tokens->isNotEmpty();

        $isOnline = false;
        $threshold = now()->subMinutes(15);

        if ($hasActiveToken && $lastSeen) {
            $isOnline = Carbon::parse($lastSeen)->gt($threshold);
        }

        // Визначаємо стан резервів та вичерпань через SiteGridReader
        $hasReserve = false;
        $hasExhausted = false;

        try {
            $rows = app(\App\Admin\SiteGridReader::class)->forSite($site);

            foreach ($rows['phone'] ?? [] as $row) {
                $state = $row['state'] ?? 'ok';
                if ($state === 'on_reserve') {
                    $hasReserve = true;
                } elseif ($state === 'exhausted') {
                    $hasExhausted = true;
                }
            }

            foreach ($rows['messenger'] ?? [] as $row) {
                $state = $row['state'] ?? 'ok';
                if ($state === 'on_reserve') {
                    $hasReserve = true;
                } elseif ($state === 'exhausted') {
                    $hasExhausted = true;
                }
            }
        } catch (\Throwable $e) {
            // мовчки ігноруємо помилки парсингу при тестах чи пустих даних
        }

        return [
            'isOnline' => $isOnline,
            'lastSeenAt' => $lastSeen,
            'hasActiveToken' => $hasActiveToken,
            'hasReserve' => $hasReserve,
            'hasExhausted' => $hasExhausted,
        ];
    }

    public function render()
    {
        $userId = auth()->id();
        $threshold = now()->subMinutes(15);

        // 1. Статистика
        $totalGroups = SiteGroup::count();
        $totalSites = Site::count();

        $activeIncidentsCount = Incident::where('status', 'new')->count();

        // Офлайн сайти: мають активний токен, але останній візит > 15 хв
        $allConnectedSites = Site::whereHas('tokens', function ($q) {
            $q->whereNull('revoked_at');
        })->get();

        $offlineSitesList = $allConnectedSites->filter(function (Site $site) use ($threshold) {
            $lastSeen = $site->tokens()->whereNull('revoked_at')->max('last_seen_at');
            if (!$lastSeen) {
                return $site->created_at->lt($threshold);
            }
            return Carbon::parse($lastSeen)->lt($threshold);
        });

        $totalOfflineSites = $offlineSitesList->count();

        // 2. Інциденти (непідтверджені)
        $incidents = Incident::where('status', 'new')
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        // 3. Улюблені (для поточного користувача)
        $favoritesCollection = UserFavorite::where('user_id', $userId)->get();

        $favGroupIds = $favoritesCollection->where('favorable_type', SiteGroup::class)->pluck('favorable_id')->all();
        $favSiteIds = $favoritesCollection->where('favorable_type', Site::class)->pluck('favorable_id')->all();

        $favoriteGroups = SiteGroup::whereIn('id', $favGroupIds)->with('sites')->get()->map(function ($group) {
            $group->sites_with_status = $group->sites->map(function ($site) {
                return [
                    'model' => $site,
                    'status' => $this->getSiteStatus($site),
                ];
            });
            return $group;
        });

        $favoriteSites = Site::whereIn('id', $favSiteIds)->get()->map(function ($site) {
            return [
                'model' => $site,
                'status' => $this->getSiteStatus($site),
            ];
        });

        // 4. Всі сайти та групи (з урахуванням пошуку)
        $searchQuery = trim($this->search);
        
        $groupsQuery = SiteGroup::query();
        $sitesQuery = Site::query();

        if ($searchQuery !== '') {
            $groupsQuery->where('name', 'like', '%' . $searchQuery . '%');
            $sitesQuery->where(function ($q) use ($searchQuery) {
                $q->where('name', 'like', '%' . $searchQuery . '%')
                  ->orWhere('domain', 'like', '%' . $searchQuery . '%');
            });
        }

        $allGroups = $groupsQuery->orderBy('name')->get();
        $allSites = $sitesQuery->orderBy('domain')->get();

        return view('livewire.dashboard', [
            'totalGroups' => $totalGroups,
            'totalSites' => $totalSites,
            'totalOfflineSites' => $totalOfflineSites,
            'activeIncidentsCount' => $activeIncidentsCount,
            'incidents' => $incidents,
            'offlineSites' => $offlineSitesList->take(15),
            'favoriteGroups' => $favoriteGroups,
            'favoriteSites' => $favoriteSites,
            'allGroups' => $allGroups,
            'allSites' => $allSites,
            'favGroupIds' => $favGroupIds,
            'favSiteIds' => $favSiteIds,
        ])->layout('components.layouts.admin');
    }
}
