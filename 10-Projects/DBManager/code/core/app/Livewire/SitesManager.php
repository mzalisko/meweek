<?php

namespace App\Livewire;

use App\Admin\AccessControl;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class SitesManager extends Component
{
    public bool $showArchived = false;

    public ?string $panelMode = null;

    public ?int $editingGroupId = null;

    public string $groupName = '';

    public function mount(): void
    {
        $this->authorizeSiteManagement();
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

    public function closePanel(): void
    {
        $this->panelMode = null;
        $this->editingGroupId = null;
        $this->groupName = '';
        $this->resetValidation();
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
