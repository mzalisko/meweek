<?php

namespace App\Livewire;

use App\Admin\AccessControl;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AccessManager extends Component
{
    public bool $panelOpen = false;

    public ?int $selectedUserId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public ?string $visiblePassword = null;

    public string $role = AccessControl::ROLE_VIEWER;

    public bool $isActive = true;

    public array $groupPermissions = [];

    public array $sitePermissions = [];

    public string $userSearch = '';

    public string $permissionSearch = '';

    public function mount(): void
    {
        $this->authorizeAccessManagement();
        $this->groupPermissions = $this->emptyGroupPermissions();
        $this->sitePermissions = $this->emptySitePermissions();
    }

    public function selectUser(int $userId): void
    {
        $this->authorizeAccessManagement();
        $this->selectedUserId = $userId;
        $this->loadSelectedUser();
        $this->panelOpen = true;
    }

    public function startCreate(): void
    {
        $this->authorizeAccessManagement();
        $this->panelOpen = true;
        $this->selectedUserId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->visiblePassword = null;
        $this->role = AccessControl::ROLE_VIEWER;
        $this->isActive = true;
        $this->groupPermissions = $this->emptyGroupPermissions();
        $this->sitePermissions = $this->emptySitePermissions();
        $this->resetValidation();
    }

    public function closePanel(): void
    {
        $this->panelOpen = false;
        $this->resetValidation();
    }

    public function applyGroupPreset(int $groupId, string $level): void
    {
        $this->authorizeAccessManagement();

        $this->groupPermissions[$groupId] = $this->permissionsForLevel($level);
    }

    public function applySitePreset(int $siteId, string $level): void
    {
        $this->authorizeAccessManagement();

        $this->sitePermissions[$siteId] = $this->permissionsForLevel($level);
    }

    public function saveUser(): void
    {
        $this->authorizeAccessManagement();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->selectedUserId),
            ],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(AccessControl::ROLES)],
            'isActive' => ['boolean'],
        ]);

        $plainPassword = $validated['password'];
        if (! $this->selectedUserId && $plainPassword === '') {
            $plainPassword = Str::password(12);
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $validated['isActive'],
        ];

        if ($plainPassword !== '') {
            $payload['password'] = Hash::make($plainPassword);
        }

        $user = $this->selectedUserId
            ? User::findOrFail($this->selectedUserId)
            : new User();

        $old = $user->exists ? $user->only(['name', 'email', 'role', 'is_active']) : null;
        $user->fill($payload)->save();

        $this->selectedUserId = $user->id;
        $this->syncPermissions($user);

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => $old ? 'user.updated' : 'user.created',
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'old' => $old,
            'new' => $user->fresh()->only(['name', 'email', 'role', 'is_active']),
        ]);

        $this->password = '';
        $this->visiblePassword = $plainPassword !== '' ? $plainPassword : null;
        $this->panelOpen = true;
        $this->dispatch('toast', message: 'Користувача збережено');
    }

    public function resetPassword(int $userId): void
    {
        $this->authorizeAccessManagement();

        $plainPassword = Str::password(12);
        $user = User::findOrFail($userId);
        $user->update(['password' => Hash::make($plainPassword)]);
        $this->logoutUser($userId, logAction: false);

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'user.password_reset',
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'new' => ['sessions_revoked' => true],
        ]);

        $this->selectedUserId = $user->id;
        $this->panelOpen = true;
        $this->visiblePassword = $plainPassword;
        $this->dispatch('toast', message: 'Пароль скинуто, активні сесії завершено');
    }

    public function logoutUser(int $userId, bool $logAction = true): void
    {
        $this->authorizeAccessManagement();

        DB::table('sessions')->where('user_id', $userId)->delete();

        if ($logAction) {
            AuditLog::create([
                'actor_type' => 'user',
                'actor_id' => auth()->id(),
                'action' => 'user.sessions_revoked',
                'subject_type' => 'User',
                'subject_id' => $userId,
                'new' => ['sessions_revoked' => true],
            ]);
        }

        $this->dispatch('toast', message: 'Сесії користувача завершено');
    }

    public function toggleUserActive(int $userId): void
    {
        $this->authorizeAccessManagement();

        if ($userId === auth()->id()) {
            $this->addError('selectedUserId', 'Не можна змінити стан власного облікового запису.');

            return;
        }

        $user = User::findOrFail($userId);
        $old = (bool) $user->is_active;
        $user->update(['is_active' => ! $old]);

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => ! $old ? 'user.activated' : 'user.deactivated',
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'old' => ['is_active' => $old],
            'new' => ['is_active' => ! $old],
        ]);

        if ($this->selectedUserId === $userId) {
            $this->loadSelectedUser();
        }
    }

    public function deactivateUser(int $userId): void
    {
        $this->authorizeAccessManagement();

        if ($userId === auth()->id()) {
            $this->addError('selectedUserId', 'Не можна вимкнути власний обліковий запис.');

            return;
        }

        $user = User::findOrFail($userId);
        $user->update(['is_active' => false]);

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'user.deactivated',
            'subject_type' => 'User',
            'subject_id' => $user->id,
            'old' => ['is_active' => true],
            'new' => ['is_active' => false],
        ]);

        if ($this->selectedUserId === $userId) {
            $this->loadSelectedUser();
        }
    }

    public function deleteUser(int $userId): void
    {
        $this->authorizeAccessManagement();

        if ($userId === auth()->id()) {
            $this->addError('selectedUserId', 'Не можна видалити власний обліковий запис.');

            return;
        }

        $user = User::findOrFail($userId);
        $old = $user->only(['name', 'email', 'role', 'is_active']);

        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->delete();

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'user.deleted',
            'subject_type' => 'User',
            'subject_id' => $userId,
            'old' => $old,
        ]);

        if ($this->selectedUserId === $userId) {
            $this->selectedUserId = null;
            $this->panelOpen = false;
        }

        $this->dispatch('toast', message: 'Користувача видалено');
    }

    public function render()
    {
        $this->authorizeAccessManagement();

        $onlineUsers = $this->onlineUsers();
        $groups = $this->filteredGroups();
        $ungroupedSites = $this->filteredUngroupedSites();
        $userSearch = mb_strtolower(trim($this->userSearch));

        return view('livewire.access-manager', [
            'users' => User::withCount(['siteAccess', 'siteGroupAccess'])
                ->when($userSearch !== '', fn ($query) => $query->where(function ($query) use ($userSearch): void {
                    $query->whereRaw('lower(name) like ?', ["%{$userSearch}%"])
                        ->orWhereRaw('lower(email) like ?', ["%{$userSearch}%"]);
                }))
                ->orderBy('name')
                ->get(),
            'groups' => $groups,
            'ungroupedSites' => $ungroupedSites,
            'selectedUser' => $this->selectedUserId ? User::find($this->selectedUserId) : null,
            'onlineUsers' => $onlineUsers,
            'onlineUserIds' => $onlineUsers->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'roles' => [
                AccessControl::ROLE_SUPERADMIN => 'Суперадмін',
                AccessControl::ROLE_MANAGER => 'Менеджер',
                AccessControl::ROLE_VIEWER => 'В’ювер',
            ],
        ])->layout('components.layouts.admin');
    }

    private function loadSelectedUser(): void
    {
        $this->groupPermissions = $this->emptyGroupPermissions();
        $this->sitePermissions = $this->emptySitePermissions();

        $user = $this->selectedUserId
            ? User::with(['siteAccess', 'siteGroupAccess'])->find($this->selectedUserId)
            : null;

        if (! $user) {
            $this->startCreate();

            return;
        }

        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->visiblePassword = null;
        $this->role = $user->role;
        $this->isActive = (bool) $user->is_active;

        foreach ($user->siteGroupAccess as $access) {
            $this->groupPermissions[$access->site_group_id] = $this->permissionArray($access);
        }

        foreach ($user->siteAccess as $access) {
            $this->sitePermissions[$access->site_id] = $this->permissionArray($access);
        }

        $this->resetValidation();
    }

    private function syncPermissions(User $user): void
    {
        if ($user->role === AccessControl::ROLE_SUPERADMIN) {
            $user->siteAccess()->delete();
            $user->siteGroupAccess()->delete();

            return;
        }

        foreach ($this->groupPermissions as $groupId => $permissions) {
            $permissions = $this->normalizedPermissions($permissions);
            if (! $this->hasAnyPermission($permissions)) {
                $user->siteGroupAccess()->where('site_group_id', $groupId)->delete();
                continue;
            }

            $user->siteGroupAccess()->updateOrCreate(
                ['site_group_id' => $groupId],
                $permissions,
            );
        }

        foreach ($this->sitePermissions as $siteId => $permissions) {
            $permissions = $this->normalizedPermissions($permissions);
            if (! $this->hasAnyPermission($permissions)) {
                $user->siteAccess()->where('site_id', $siteId)->delete();
                continue;
            }

            $user->siteAccess()->updateOrCreate(
                ['site_id' => $siteId],
                $permissions,
            );
        }
    }

    private function onlineUsers(): Collection
    {
        $threshold = now()->subMinutes(5)->timestamp;

        return DB::table('sessions')
            ->join('users', 'sessions.user_id', '=', 'users.id')
            ->where('sessions.last_activity', '>=', $threshold)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw('max(sessions.last_activity) as last_activity'),
                DB::raw('count(sessions.id) as sessions_count'),
            ])
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('last_activity')
            ->get();
    }

    private function authorizeAccessManagement(): void
    {
        abort_unless(app(AccessControl::class)->canManageAccess(auth()->user()), 403);
    }

    private function filteredGroups(): Collection
    {
        $search = mb_strtolower(trim($this->permissionSearch));

        return SiteGroup::with(['sites' => fn ($query) => $query->orderBy('domain')])
            ->orderBy('name')
            ->get()
            ->map(function (SiteGroup $group) use ($search): ?SiteGroup {
                if ($search === '' || str_contains(mb_strtolower($group->name), $search)) {
                    return $group;
                }

                $sites = $group->sites->filter(fn (Site $site) => str_contains(mb_strtolower($site->domain.' '.$site->name), $search))->values();
                if ($sites->isEmpty()) {
                    return null;
                }

                $group->setRelation('sites', $sites);

                return $group;
            })
            ->filter()
            ->values();
    }

    private function filteredUngroupedSites(): Collection
    {
        $search = mb_strtolower(trim($this->permissionSearch));

        return Site::whereNull('site_group_id')
            ->orderBy('domain')
            ->get()
            ->filter(fn (Site $site) => $search === '' || str_contains(mb_strtolower($site->domain.' '.$site->name), $search))
            ->values();
    }

    private function emptyGroupPermissions(): array
    {
        return SiteGroup::query()
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => $this->blankPermissions()])
            ->all();
    }

    private function emptySitePermissions(): array
    {
        return Site::query()
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => $this->blankPermissions()])
            ->all();
    }

    private function blankPermissions(): array
    {
        return [
            'can_view' => false,
            'can_edit' => false,
            'can_delete' => false,
            'can_publish' => false,
        ];
    }

    private function permissionArray(object $access): array
    {
        return [
            'can_view' => (bool) $access->can_view,
            'can_edit' => (bool) $access->can_edit,
            'can_delete' => (bool) $access->can_delete,
            'can_publish' => (bool) $access->can_publish,
        ];
    }

    private function normalizedPermissions(array $permissions): array
    {
        $canPublish = (bool) ($permissions['can_publish'] ?? false);
        $canDelete = (bool) ($permissions['can_delete'] ?? false);
        $canEdit = (bool) ($permissions['can_edit'] ?? false) || $canPublish;

        return [
            'can_view' => (bool) ($permissions['can_view'] ?? false) || $canEdit || $canDelete,
            'can_edit' => $canEdit,
            'can_delete' => $canDelete,
            'can_publish' => $canPublish,
        ];
    }

    private function hasAnyPermission(array $permissions): bool
    {
        return $permissions['can_view'] || $permissions['can_edit'] || $permissions['can_delete'] || $permissions['can_publish'];
    }

    private function permissionsForLevel(string $level): array
    {
        return match ($level) {
            'view' => ['can_view' => true, 'can_edit' => false, 'can_delete' => false, 'can_publish' => false],
            'edit' => ['can_view' => true, 'can_edit' => true, 'can_delete' => false, 'can_publish' => false],
            'delete' => ['can_view' => true, 'can_edit' => true, 'can_delete' => true, 'can_publish' => false],
            'publish' => ['can_view' => true, 'can_edit' => true, 'can_delete' => true, 'can_publish' => true],
            default => $this->blankPermissions(),
        };
    }
}
