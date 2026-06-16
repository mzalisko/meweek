<?php

namespace App\Livewire;

use App\Admin\AccessControl;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use Livewire\Component;

class SitesManager extends Component
{
    public bool $showArchived = false;

    public ?string $panelMode = null;

    public function mount(): void
    {
        $this->authorizeSiteManagement();
    }

    public function render()
    {
        $this->authorizeSiteManagement();

        $groups = SiteGroup::query()
            ->when($this->showArchived, fn ($query) => $query->withTrashed())
            ->with(['sites' => function ($query) {
                $query->when($this->showArchived, fn ($q) => $q->withTrashed())
                    ->orderBy('domain');
            }])
            ->orderBy('name')
            ->get();

        $ungroupedSites = Site::query()
            ->whereNull('site_group_id')
            ->when($this->showArchived, fn ($query) => $query->withTrashed())
            ->orderBy('domain')
            ->get();

        return view('livewire.sites-manager', [
            'groups' => $groups,
            'ungroupedSites' => $ungroupedSites,
            'valueCounts' => $this->valueCounts(),
        ])->layout('components.layouts.admin');
    }

    private function valueCounts(): array
    {
        return DataValue::query()
            ->where('scope_type', 'site')
            ->selectRaw('scope_id, count(*) as aggregate')
            ->groupBy('scope_id')
            ->pluck('aggregate', 'scope_id')
            ->all();
    }

    private function authorizeSiteManagement(): void
    {
        abort_unless(app(AccessControl::class)->canManageAccess(auth()->user()), 403);
    }
}
