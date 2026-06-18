<x-slot name="breadcrumb">
    <div class="flex items-center gap-3 ml-2">
        <span class="text-mut text-sm select-none">/</span>
        <div class="inline-flex items-center bg-[#f4f5f3] px-3 py-1.5 rounded-lg border border-[#e3e5e1] text-xs font-bold text-ink select-none">
            Користувачі
        </div>
    </div>
</x-slot>

<x-slot name="context">
    <div class="bg-acc-bg border-b border-acc-bd px-[18px] py-2 text-xs text-acc-tx flex gap-3 items-center">
        @svg('user')
        <span>Користувачі, ролі, доступ до груп і сайтів, активні сесії.</span>
    </div>
</x-slot>

<div class="relative h-full w-full p-4">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[18px] font-semibold text-acc-tx">Користувачі</h1>
            <p class="mt-0.5 text-[12px] text-mut">Список облікових записів, стан, онлайн і доступи. Налаштування відкриваються у правій панелі.</p>
        </div>

        <button type="button" wire:click="startCreate"
            class="inline-flex items-center gap-1.5 rounded-lg bg-acc px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
            @svg('plus') Додати користувача
        </button>
    </div>

    <div class="mb-3 flex items-center gap-2 rounded-lg bg-[#eef1ee] px-3 py-2 w-[360px] max-w-full focus-within:bg-white">
        <span class="text-mut shrink-0">@svg('search')</span>
        <input wire:model.live.debounce.250ms="userSearch" type="text" placeholder="Пошук за ім’ям або email"
            class="min-w-0 flex-1 bg-transparent outline-none text-xs text-ink placeholder-mut border-0 shadow-none focus:ring-0 focus:outline-none">
        @if($userSearch !== '')
            <button wire:click="$set('userSearch', '')" class="text-mut hover:text-ink" aria-label="Очистити пошук">@svg('x')</button>
        @endif
    </div>

    <div class="w-full overflow-hidden rounded-lg border border-[#dfe3e0] bg-white shadow-[0_1px_2px_rgba(26,34,29,0.04)]">
        <table class="w-full min-w-full table-fixed text-[12px]">
            <thead class="bg-[#f6f8f6] text-[10px] uppercase tracking-wide text-mut">
                <tr class="border-b border-[#dfe3e0]">
                    <th class="w-[34%] px-3 py-2.5 text-left font-semibold">Користувач</th>
                    <th class="w-[13%] px-3 py-2.5 text-left font-semibold">Роль</th>
                    <th class="w-[13%] px-3 py-2.5 text-left font-semibold">Стан</th>
                    <th class="w-[16%] px-3 py-2.5 text-left font-semibold">Доступи</th>
                    <th class="w-[24%] px-3 py-2.5 text-right font-semibold">Дії</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php
                        $isOnline = in_array($user->id, $onlineUserIds, true);
                        $accessCount = $user->site_access_count + $user->site_group_access_count;
                    @endphp
                    <tr class="border-b border-[#edf0ed] last:border-b-0 hover:bg-[#fafbfa]">
                        <td class="px-3 py-3 align-middle">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-acc-bg text-[12px] font-semibold text-acc-tx">
                                    {{ mb_substr($user->name, 0, 1) }}
                                    @if($isOnline)
                                        <span class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-ok-tx" aria-hidden="true"></span>
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <button type="button" wire:click="selectUser({{ $user->id }})" class="font-semibold text-ink truncate hover:text-acc-tx">
                                            {{ $user->name }}
                                        </button>
                                        @if($isOnline)
                                            <span class="shrink-0 rounded-md bg-ok-bg px-1.5 py-0.5 text-[10px] font-semibold text-ok-tx">онлайн</span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 truncate text-[11px] text-mut">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>

                        <td class="px-3 py-3 align-middle">
                            <span class="rounded-md bg-acc-bg px-2 py-0.5 text-[11px] font-semibold text-acc-tx">{{ $roles[$user->role] ?? $user->role }}</span>
                        </td>

                        <td class="px-3 py-3 align-middle">
                            <span class="rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $user->is_active ? 'bg-ok-bg text-ok-tx' : 'bg-bad-bg text-bad-tx' }}">
                                {{ $user->is_active ? 'активний' : 'вимкнено' }}
                            </span>
                        </td>

                        <td class="px-3 py-3 align-middle text-[11px] text-mut">
                            @if($user->role === 'superadmin')
                                усі групи й сайти
                            @else
                                {{ $accessCount }} правил
                            @endif
                        </td>

                        <td class="px-3 py-3 align-middle">
                            <div class="grid grid-cols-[112px_36px] justify-end gap-1.5">
                                <button type="button" wire:click="selectUser({{ $user->id }})"
                                    class="inline-flex h-8 w-full items-center justify-center gap-1 whitespace-nowrap rounded-lg border border-[#dfe3e0] px-2.5 text-[11px] font-semibold text-acc-tx hover:border-acc hover:bg-acc-bg">
                                    @svg('edit') Редагувати
                                </button>
                                @if($user->id !== auth()->id())
                                    <button type="button" wire:click="toggleUserActive({{ $user->id }})"
                                        class="inline-flex h-8 w-9 items-center justify-center rounded-lg border border-[#dfe3e0] text-[11px] text-mut hover:border-acc hover:text-acc-tx"
                                        title="{{ $user->is_active ? 'Вимкнути' : 'Увімкнути' }}">
                                        @if($user->is_active) @svg('ban') @else @svg('eye') @endif
                                    </button>
                                @else
                                    <span class="block h-8 w-9" aria-hidden="true"></span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-sm text-mut">Користувачів не знайдено</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 rounded-lg border border-[#dfe3e0] bg-white">
        <div class="border-b border-[#e3e5e1] px-4 py-3">
            <h2 class="text-[14px] font-semibold text-acc-tx">Онлайн зараз</h2>
            <p class="mt-0.5 text-[11px] text-mut">Активність за останні 5 хвилин.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 p-3">
            @forelse($onlineUsers as $online)
                <div class="rounded-lg bg-[#f7f8f7] px-3 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-ink truncate">{{ $online->name }}</span>
                        <span class="rounded-md bg-ok-bg px-1.5 py-0.5 text-[10px] text-ok-tx">{{ $online->sessions_count }} сес.</span>
                    </div>
                    <div class="text-[11px] text-mut truncate">{{ $online->email }}</div>
                    <div class="mt-1 text-[10px] text-mut">Остання дія: {{ \Carbon\Carbon::createFromTimestamp($online->last_activity)->diffForHumans() }}</div>
                </div>
            @empty
                <div class="rounded-lg bg-[#f7f8f7] px-3 py-4 text-center text-[12px] text-mut">Активних сесій немає</div>
            @endforelse
        </div>
    </div>
    @if($panelOpen)
    <div class="fixed inset-0 z-40 cursor-pointer bg-[#111827]/10" wire:click.self="closePanel">
        <aside wire:click.stop class="fixed right-0 top-0 bottom-0 w-[820px] max-w-[calc(100vw-24px)] cursor-default bg-white border-l border-[#dfe3e0] shadow-[-18px_0_40px_rgba(28,34,30,0.12)] overflow-y-auto text-[13px]">
            <div class="sticky top-0 z-10 bg-white border-b border-[#e3e5e1] px-4 py-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <b class="text-acc-tx text-[15px] flex items-center gap-1.5">@svg('user') {{ $selectedUserId ? 'Налаштування користувача' : 'Новий користувач' }}</b>
                        <div class="mt-1 text-[11px] text-mut truncate">{{ $selectedUser?->email ?? 'Створення нового облікового запису' }}</div>
                    </div>
                    <button type="button" wire:click="closePanel" class="text-mut hover:text-ink shrink-0" aria-label="Закрити">@svg('x')</button>
                </div>
            </div>

            <div class="p-4">
                @if($visiblePassword)
                    <div class="mb-4 rounded-lg border border-warn-tx/40 bg-warn-bg px-3 py-2">
                        <div class="text-[12px] font-semibold text-warn-tx">Тимчасовий пароль видно лише зараз</div>
                        <div class="mt-1 font-mono text-[13px] text-ink select-all">{{ $visiblePassword }}</div>
                    </div>
                @endif

                @error('selectedUserId')
                    <div class="mb-4 rounded-lg border border-bad-tx/30 bg-bad-bg px-3 py-2 text-[12px] text-bad-tx">{{ $message }}</div>
                @enderror

                <section class="rounded-lg border border-[#dfe3e0]">
                    <div class="border-b border-[#edf0ed] bg-[#f6f8f6] px-3 py-2 text-[11px] uppercase tracking-wide text-mut">Дані користувача</div>
                    <div class="grid grid-cols-1 gap-3 p-3 xl:grid-cols-2">
                        <label class="block">
                            <span class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Ім’я</span>
                            <input wire:model="name" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:outline-none focus:border-acc">
                            @error('name')<span class="text-[11px] text-bad-tx">{{ $message }}</span>@enderror
                        </label>

                        <label class="block">
                            <span class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Email</span>
                            <input wire:model="email" type="email" class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:outline-none focus:border-acc">
                            @error('email')<span class="text-[11px] text-bad-tx">{{ $message }}</span>@enderror
                        </label>

                        <div class="block">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="block text-mut uppercase tracking-[.06em] text-[11px]">Пароль</span>
                                <span class="shrink-0 text-[10px] text-mut">{{ $selectedUserId ? 'порожньо = без змін' : 'згенеровано' }}</span>
                            </div>
                            <div class="grid grid-cols-[minmax(0,1fr)_38px] gap-2">
                                <input wire:model="password" type="text" placeholder="{{ $selectedUserId ? 'новий пароль або порожньо' : 'можна змінити вручну' }}" class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:outline-none focus:border-acc">
                                <button type="button" wire:click="generatePassword" title="Згенерувати пароль" aria-label="Згенерувати пароль"
                                    class="inline-flex h-[38px] w-[38px] items-center justify-center rounded-lg border border-[#dfe3e0] text-mut hover:border-acc hover:bg-acc-bg hover:text-acc-tx">
                                    @svg('refresh')
                                </button>
                            </div>
                            @error('password')<span class="text-[11px] text-bad-tx">{{ $message }}</span>@enderror
                        </div>

                        <div class="grid grid-cols-[minmax(0,1fr)_132px] gap-3">
                            <label class="block">
                                <span class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Роль</span>
                                <select wire:model.live="role" class="w-full rounded-lg border border-[#dfe3e0] bg-white px-3 py-2 focus:outline-none focus:border-acc">
                                    @foreach($roles as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('role')<span class="text-[11px] text-bad-tx">{{ $message }}</span>@enderror
                            </label>

                            <label class="mt-5 inline-flex h-[38px] items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-[#dfe3e0] px-3 py-2">
                                <input wire:model="isActive" type="checkbox" class="accent-[#54708c]" @disabled($selectedUserId === auth()->id())>
                                <span class="text-xs text-ink">Активний</span>
                            </label>
                        </div>
                    </div>
                </section>

                @php
                    $permissionGridStyle = 'min-width: 860px; grid-template-columns: minmax(190px, 1fr) repeat(7, 80px);';
                @endphp

                <section class="mt-4 rounded-lg border border-[#dfe3e0]">
                    <div class="border-b border-[#edf0ed] bg-[#f6f8f6] px-3 py-2 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div class="text-[11px] uppercase tracking-wide text-mut">Сесії й пароль</div>
                        @if($selectedUser)
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button type="button" wire:click="resetPassword({{ $selectedUser->id }})" wire:confirm="Створити тимчасовий пароль і завершити активні сесії?"
                                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-lg border border-[#dfe3e0] px-2.5 py-1 text-[11px] text-mut hover:border-acc hover:text-acc-tx">@svg('key') Тимчасовий пароль</button>
                                <button type="button" wire:click="logoutUser({{ $selectedUser->id }})" wire:confirm="Завершити всі сесії цього користувача?"
                                    class="whitespace-nowrap rounded-lg border border-[#dfe3e0] px-2.5 py-1 text-[11px] text-mut hover:border-bad-tx hover:text-bad-tx">Викинути з сесій</button>
                            </div>
                        @endif
                    </div>
                    <div class="p-3 text-[12px] text-mut">
                        Паролі зберігаються hash-ем. Поточний пароль не показується; новий тимчасовий пароль видно один раз після створення або генерації.
                    </div>
                </section>

                <section class="mt-4 rounded-lg border border-[#dfe3e0]">
                    <div class="border-b border-[#edf0ed] bg-[#f6f8f6] px-3 py-2">
                        <div class="text-[11px] uppercase tracking-wide text-mut">Доступ до логів</div>
                        <div class="mt-0.5 text-[11px] text-mut">Глобальні вкладки аудиту. Історія змін і Failover для конкретних сайтів налаштовуються нижче в матриці.</div>
                    </div>
                    <div class="grid grid-cols-1 gap-2 p-3 md:grid-cols-2">
                        <label class="flex items-start gap-2 rounded-lg border border-[#dfe3e0] px-3 py-2">
                            <input wire:model="canViewUserLogs" type="checkbox" @disabled($role === 'superadmin') class="mt-0.5 h-4 w-4 rounded border-[#c8cec9] text-acc accent-acc focus:ring-acc focus:ring-offset-0 disabled:opacity-40">
                            <span>
                                <span class="block text-[12px] font-semibold text-ink">Логи користувачів</span>
                                <span class="block text-[11px] text-mut">Входи, виходи, невдалі входи, паролі й сесії.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 rounded-lg border border-[#dfe3e0] px-3 py-2">
                            <input wire:model="canViewSystemLogs" type="checkbox" @disabled($role === 'superadmin') class="mt-0.5 h-4 w-4 rounded border-[#c8cec9] text-acc accent-acc focus:ring-acc focus:ring-offset-0 disabled:opacity-40">
                            <span>
                                <span class="block text-[12px] font-semibold text-ink">Системні логи</span>
                                <span class="block text-[11px] text-mut">Сайти, групи, токени, вебхуки та службові події.</span>
                            </span>
                        </label>
                    </div>
                </section>

                <section class="mt-4 rounded-lg border border-[#dfe3e0]">
                    <div class="border-b border-[#edf0ed] bg-[#f6f8f6] px-3 py-2">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-[11px] uppercase tracking-wide text-mut">Матриця дозволів</div>
                                <div class="mt-0.5 text-[11px] text-mut">Для багатьох сайтів: пошук, групові пресети і точні override-и на рівні сайта.</div>
                            </div>
                            @if($role === 'superadmin')
                                <span class="rounded-md bg-acc-bg px-2 py-1 text-[11px] font-semibold text-acc-tx">Повний доступ</span>
                            @endif
                        </div>

                        <div class="mt-2 flex items-center gap-2 rounded-lg bg-white px-3 py-1.5 max-w-[360px]">
                            <span class="text-mut">@svg('search')</span>
                            <input wire:model.live.debounce.250ms="permissionSearch" type="text" placeholder="Пошук групи або домену"
                                class="min-w-0 flex-1 bg-transparent outline-none text-xs text-ink placeholder-mut border-0 shadow-none focus:ring-0 focus:outline-none">
                            @if($permissionSearch !== '')
                                <button type="button" wire:click="$set('permissionSearch', '')" class="text-mut hover:text-ink" aria-label="Очистити пошук">@svg('x')</button>
                            @endif
                        </div>
                    </div>

                    <div class="max-h-[520px] overflow-y-auto p-3 space-y-3">
                        @foreach($groups as $group)
                            @php
                                $gp = $groupPermissions[$group->id] ?? ['can_view' => false, 'can_edit' => false, 'can_delete' => false, 'can_publish' => false, 'can_view_history' => false, 'can_view_failover' => false, 'can_view_prices' => false];
                                $groupLevel = $gp['can_publish'] ? 'publish' : ($gp['can_delete'] ? 'delete' : ($gp['can_edit'] ? 'edit' : ($gp['can_view'] ? 'view' : 'none')));
                                $groupLevelLabels = ['none' => 'немає', 'view' => 'перегляд', 'edit' => 'редагування', 'delete' => 'видалення', 'publish' => 'повний'];
                            @endphp
                            <div class="rounded-lg border border-[#dfe3e0] overflow-x-auto">
                                <div class="sticky top-0 z-[1] bg-acc-bg border-b border-acc-bd px-3 py-2 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <div class="font-semibold text-acc-tx truncate">{{ $group->name }}</div>
                                            <span class="shrink-0 rounded-md bg-white border border-acc-bd px-1.5 py-0.5 text-[10px] text-acc-tx">{{ $groupLevelLabels[$groupLevel] }}</span>
                                        </div>
                                        <div class="text-[10px] text-mut">{{ $group->sites->count() }} сайтів у поточному фільтрі</div>
                                    </div>
                                </div>

                                <div class="grid gap-2 bg-[#fafbfa] px-3 py-1.5 text-[10px] uppercase tracking-wide text-mut" style="{{ $permissionGridStyle }}">
                                    <div>Сайт / Ресурс</div>
                                    <div class="text-center whitespace-nowrap">Перегляд</div>
                                    <div class="text-center whitespace-nowrap">Редаг.</div>
                                    <div class="text-center whitespace-nowrap">Видал.</div>
                                    <div class="text-center whitespace-nowrap">Публ.</div>
                                    <div class="text-center whitespace-nowrap">Історія</div>
                                    <div class="text-center whitespace-nowrap">Failover</div>
                                    <div class="text-center whitespace-nowrap">Ціни</div>
                                </div>

                                {{-- Рядок для налаштування всієї групи --}}
                                <div class="grid gap-2 items-center bg-[#f0f4f1] border-b border-[#edf0ed] px-3 py-2" style="{{ $permissionGridStyle }}">
                                    <div class="min-w-0">
                                        <div class="font-bold text-acc-tx truncate">Уся група: {{ $group->name }}</div>
                                        <div class="text-[10px] text-mut truncate">Налаштування для всієї групи</div>
                                    </div>
                                    @foreach(['can_view', 'can_edit', 'can_delete', 'can_publish', 'can_view_history', 'can_view_failover', 'can_view_prices'] as $permission)
                                        <label class="flex justify-center">
                                            <input wire:model="groupPermissions.{{ $group->id }}.{{ $permission }}" type="checkbox" @disabled($role === 'superadmin') class="h-4 w-4 rounded border-[#c8cec9] text-acc accent-acc focus:ring-acc focus:ring-offset-0 disabled:opacity-40">
                                        </label>
                                    @endforeach
                                </div>

                                @foreach($group->sites as $site)
                                    <div class="grid gap-2 items-center border-t border-[#edf0ed] px-3 py-2" style="{{ $permissionGridStyle }}">
                                        <div class="min-w-0">
                                            <div class="font-mono text-[11px] text-ink truncate">{{ $site->domain }}</div>
                                            <div class="text-[10px] text-mut truncate">{{ $site->name }}</div>
                                        </div>
                                        @foreach(['can_view', 'can_edit', 'can_delete', 'can_publish', 'can_view_history', 'can_view_failover', 'can_view_prices'] as $permission)
                                            <label class="flex justify-center">
                                                <input wire:model="sitePermissions.{{ $site->id }}.{{ $permission }}" type="checkbox" @disabled($role === 'superadmin') class="h-4 w-4 rounded border-[#c8cec9] text-acc accent-acc focus:ring-acc focus:ring-offset-0 disabled:opacity-40">
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        @if($ungroupedSites->isNotEmpty())
                            <div class="rounded-lg border border-[#dfe3e0] overflow-x-auto">
                                <div class="bg-[#f6f8f6] border-b border-[#edf0ed] px-3 py-2 font-semibold text-acc-tx">Без групи</div>
                                <div class="grid gap-2 bg-[#fafbfa] px-3 py-1.5 text-[10px] uppercase tracking-wide text-mut border-b border-[#dfe3e0]" style="{{ $permissionGridStyle }}">
                                    <div>Сайт</div>
                                    <div class="text-center whitespace-nowrap">Перегляд</div>
                                    <div class="text-center whitespace-nowrap">Редаг.</div>
                                    <div class="text-center whitespace-nowrap">Видал.</div>
                                    <div class="text-center whitespace-nowrap">Публ.</div>
                                    <div class="text-center whitespace-nowrap">Історія</div>
                                    <div class="text-center whitespace-nowrap">Failover</div>
                                    <div class="text-center whitespace-nowrap">Ціни</div>
                                </div>
                                @foreach($ungroupedSites as $site)
                                    <div class="grid gap-2 items-center border-t border-[#edf0ed] px-3 py-2" style="{{ $permissionGridStyle }}">
                                        <div class="min-w-0">
                                            <div class="font-mono text-[11px] text-ink truncate">{{ $site->domain }}</div>
                                            <div class="text-[10px] text-mut truncate">{{ $site->name }}</div>
                                        </div>
                                        @foreach(['can_view', 'can_edit', 'can_delete', 'can_publish', 'can_view_history', 'can_view_failover', 'can_view_prices'] as $permission)
                                            <label class="flex justify-center">
                                                <input wire:model="sitePermissions.{{ $site->id }}.{{ $permission }}" type="checkbox" @disabled($role === 'superadmin') class="h-4 w-4 rounded border-[#c8cec9] text-acc accent-acc focus:ring-acc focus:ring-offset-0 disabled:opacity-40">
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($groups->isEmpty() && $ungroupedSites->isEmpty())
                            <div class="rounded-lg bg-[#f7f8f7] px-3 py-6 text-center text-[12px] text-mut">Нічого не знайдено</div>
                        @endif
                    </div>
                </section>

                <div class="sticky bottom-0 mt-4 -mx-4 -mb-4 bg-white border-t border-[#e3e5e1] px-4 py-3 flex items-center justify-between gap-3">
                    <div>
                        @if($selectedUser && $selectedUser->id !== auth()->id())
                            <button type="button" wire:click="deleteUser({{ $selectedUser->id }})" wire:confirm="Видалити користувача і завершити його сесії?"
                                class="whitespace-nowrap rounded-lg border border-bad-tx/40 px-3 py-1.5 text-xs text-bad-tx hover:bg-bad-bg">Видалити</button>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="closePanel" class="whitespace-nowrap rounded-lg border border-[#dfe3e0] px-3 py-1.5 text-xs text-mut hover:border-acc hover:text-acc-tx">Закрити</button>
                        <button type="button" wire:click="saveUser" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg bg-acc px-4 py-1.5 text-xs font-semibold text-white hover:opacity-90">
                            @svg('check') Зберегти
                        </button>
                    </div>
                </div>
            </div>
        </aside>
    </div>
    @endif
</div>
