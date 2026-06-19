<x-slot name="breadcrumb">
    <div class="flex items-center gap-3 ml-2">
        <span class="text-mut text-sm select-none">/</span>
        <div class="inline-flex items-center bg-[#f4f5f3] px-3 py-1.5 rounded-lg border border-[#e3e5e1] text-xs font-bold text-ink select-none">
            Сайти і групи
        </div>
    </div>
</x-slot>

<x-slot name="context">
    <div class="bg-acc-bg border-b border-acc-bd px-[18px] py-2 text-xs text-acc-tx flex gap-3 items-center">
        @svg('sites')
        <span>Групи, сайти, токени і зв’язок. Звідси — вхід у дані кожного сайту.</span>
    </div>
</x-slot>

@php
    $siteSectionKeys = $groups->pluck('id')->map(fn ($id) => 'group-'.$id)->values();
    if ($ungroupedSites->isNotEmpty()) {
        $siteSectionKeys->push('ungrouped');
    }
@endphp

<div
    class="relative h-full w-full p-4"
    x-data="{
        collapsed: {},
        sectionKeys: {{ json_encode($siteSectionKeys->all()) }},
        isOpen(key) { return this.collapsed[key] !== true },
        toggle(key) { this.collapsed[key] = this.isOpen(key) },
        expandAll() { this.sectionKeys.forEach(key => this.collapsed[key] = false) },
        collapseAll() { this.sectionKeys.forEach(key => this.collapsed[key] = true) },
    }"
>
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[18px] font-semibold text-acc-tx">Сайти і групи</h1>
            <p class="mt-0.5 text-[12px] text-mut">Створюйте групи й сайти, керуйте токенами та заходьте в дані сайта.</p>
        </div>
        @if($canManageSites)
            <div class="flex items-center gap-3">
                <label class="inline-flex items-center gap-2 text-xs text-mut">
                    <input type="checkbox" wire:model.live="showArchived" class="rounded border-[#cfd6d0]">
                    Показати архів
                </label>
                <button type="button" wire:click="startCreateGroup"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-[#dfe3e0] px-3 py-2 text-xs font-semibold text-acc-tx hover:border-acc hover:bg-acc-bg">
                    @svg('plus') Створити групу
                </button>
                <button type="button" wire:click="startCreateSite"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-acc px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                    @svg('plus') Створити сайт
                </button>
            </div>
        @endif
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="flex w-[360px] max-w-full items-center gap-2 rounded-lg bg-[#eef1ee] px-3 py-2 focus-within:bg-white">
            <span class="text-mut shrink-0">@svg('search')</span>
            <input wire:model.live.debounce.250ms="siteSearch" type="text" placeholder="Пошук групи або домену"
                class="min-w-0 flex-1 bg-transparent outline-none text-xs text-ink placeholder-mut border-0 shadow-none focus:ring-0 focus:outline-none">
            @if($siteSearch !== '')
                <button type="button" wire:click="$set('siteSearch', '')" class="text-mut hover:text-ink" aria-label="Очистити пошук">@svg('x')</button>
            @endif
        </div>

        @if($siteSectionKeys->isNotEmpty())
            <div class="flex shrink-0 items-center gap-1.5">
                <button type="button" @click="expandAll()"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#dfe3e0] text-mut hover:border-acc hover:text-acc-tx"
                    title="Розгорнути всі" aria-label="Розгорнути всі">
                    @svg('chevron-down')
                </button>
                <button type="button" @click="collapseAll()"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#dfe3e0] text-mut hover:border-acc hover:text-acc-tx"
                    title="Згорнути всі" aria-label="Згорнути всі">
                    @svg('chevron-up')
                </button>
            </div>
        @endif
    </div>

    <div class="space-y-4">
        @forelse($groups as $group)
            <div wire:key="group-{{ $group->id }}" @class([
                'rounded-lg border bg-white',
                'border-[#dfe3e0]' => ! $group->trashed(),
                'border-dashed border-[#cdb9b4]' => $group->trashed(),
            ])>
                <div class="flex items-center justify-between gap-2 border-b border-[#edf0ed] px-3 py-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <button type="button"
                            @click="toggle('group-{{ $group->id }}')"
                            :aria-expanded="(isOpen('group-{{ $group->id }}') || {{ $siteSearch !== '' ? 'true' : 'false' }}).toString()"
                            aria-label="Згорнути або розгорнути групу"
                            class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-mut hover:bg-acc-bg hover:text-acc-tx">
                            <span class="inline-flex transition-transform duration-150" :class="isOpen('group-{{ $group->id }}') || {{ $siteSearch !== '' ? 'true' : 'false' }} ? '' : '-rotate-90'">@svg('chevron-down')</span>
                        </button>
                        <span class="font-semibold text-ink truncate">{{ $group->name }}</span>
                        @if($group->trashed())
                            <span class="rounded bg-[#f3e7e4] px-1.5 py-0.5 text-[10px] text-[#a85c52]">архів</span>
                        @endif
                        <span class="text-[11px] text-mut">{{ $groupSiteCounts[$group->id] ?? $group->sites->count() }} сайт(ів)</span>
                    </div>
                    @if($canManageSites)
                        <div class="flex shrink-0 items-center gap-1.5">
                            @if($group->trashed())
                                <button type="button" wire:click="restoreGroup({{ $group->id }})"
                                    class="inline-flex h-7 items-center gap-1 rounded-md border border-[#dfe3e0] px-2 text-[11px] font-semibold text-acc-tx hover:border-acc hover:bg-acc-bg">
                                    @svg('check') Відновити
                                </button>
                            @else
                                <button type="button" wire:click="startCreateSite({{ $group->id }})"
                                    class="inline-flex h-7 items-center gap-1 rounded-md border border-[#dfe3e0] px-2 text-[11px] font-semibold text-acc-tx hover:border-acc hover:bg-acc-bg">
                                    @svg('plus') Сайт
                                </button>
                                <button type="button" wire:click="editGroup({{ $group->id }})" aria-label="Редагувати групу"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-[#dfe3e0] text-mut hover:border-acc hover:bg-acc-bg hover:text-acc-tx">
                                    @svg('edit')
                                </button>
                                <button type="button" wire:click="archiveGroup({{ $group->id }})"
                                    wire:confirm="Заархівувати групу «{{ $group->name }}» разом з усіма її сайтами? Дані лишаться, сайти стануть прихованими."
                                    aria-label="Заархівувати групу"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-[#dfe3e0] text-mut hover:border-[#cdb9b4] hover:bg-[#f3e7e4] hover:text-[#a85c52]">
                                    @svg('trash')
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div x-show="isOpen('group-{{ $group->id }}') || {{ $siteSearch !== '' ? 'true' : 'false' }}">
                    @forelse($group->sites as $site)
                        @include('livewire.partials.site-row', ['site' => $site, 'depth' => 0, 'canManageSites' => $canManageSites])
                        @foreach($site->children as $child)
                            @include('livewire.partials.site-row', ['site' => $child, 'depth' => 1, 'canManageSites' => $canManageSites])
                        @endforeach
                    @empty
                        <div class="px-3 py-2 text-[11px] text-mut">Немає сайтів у групі.</div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-[#dfe3e0] px-3 py-6 text-center text-xs text-mut">
                Ще немає груп. Натисніть «Створити групу», щоб почати.
            </div>
        @endforelse

        @if($ungroupedSites->isNotEmpty())
            <div class="rounded-lg border border-[#dfe3e0] bg-white">
                <button type="button"
                    @click="toggle('ungrouped')"
                    :aria-expanded="(isOpen('ungrouped') || {{ $siteSearch !== '' ? 'true' : 'false' }}).toString()"
                    class="flex w-full items-center gap-2 border-b border-[#edf0ed] px-3 py-2 text-left font-semibold text-ink hover:bg-[#eef1ee]">
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-mut">
                        <span class="inline-flex transition-transform duration-150" :class="isOpen('ungrouped') || {{ $siteSearch !== '' ? 'true' : 'false' }} ? '' : '-rotate-90'">@svg('chevron-down')</span>
                    </span>
                    <span>Без групи</span>
                    <span class="text-[11px] font-normal text-mut">{{ $ungroupedSites->count() }} сайт(ів)</span>
                </button>
                <div x-show="isOpen('ungrouped') || {{ $siteSearch !== '' ? 'true' : 'false' }}">
                    @foreach($ungroupedSites as $site)
                        @include('livewire.partials.site-row', ['site' => $site, 'depth' => 0, 'canManageSites' => $canManageSites])
                        @foreach($site->children as $child)
                            @include('livewire.partials.site-row', ['site' => $child, 'depth' => 1, 'canManageSites' => $canManageSites])
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if($canManageSites && $panelMode === 'group')
        <div class="fixed inset-0 z-20 bg-[rgba(20,26,22,0.28)]" wire:click="closePanel"></div>
        <aside wire:click.stop
            class="fixed right-0 top-0 bottom-0 z-30 w-[460px] max-w-[calc(100vw-24px)] overflow-y-auto border-l border-[#dfe3e0] bg-white text-[13px] shadow-[-18px_0_40px_rgba(28,34,30,0.12)]">
            <div class="flex items-center justify-between border-b border-[#edf0ed] px-4 py-3">
                <h2 class="text-[15px] font-semibold text-acc-tx">
                    {{ $editingGroupId ? 'Редагувати групу' : 'Нова група' }}
                </h2>
                <button type="button" wire:click="closePanel" class="text-mut hover:text-ink shrink-0" aria-label="Закрити">@svg('x')</button>
            </div>

            <form wire:submit="saveGroup" class="space-y-4 px-4 py-4">
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Назва групи</label>
                    <input wire:model="groupName" type="text" autofocus
                        class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                    @error('groupName')
                        <p class="mt-1 text-[12px] text-bad-tx">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-acc px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                        Зберегти
                    </button>
                    <button type="button" wire:click="closePanel"
                        class="rounded-lg border border-[#dfe3e0] px-3 py-2 text-xs font-semibold text-mut hover:border-acc hover:text-acc-tx">
                        Скасувати
                    </button>
                </div>
            </form>
        </aside>
    @endif

    @if($canManageSites && $panelMode === 'site')
        <div class="fixed inset-0 z-20 bg-[rgba(20,26,22,0.28)]" wire:click="closePanel"></div>
        <aside wire:click.stop
            class="fixed right-0 top-0 bottom-0 z-30 w-[460px] max-w-[calc(100vw-24px)] overflow-y-auto border-l border-[#dfe3e0] bg-white text-[13px] shadow-[-18px_0_40px_rgba(28,34,30,0.12)]">
            <div class="flex items-center justify-between border-b border-[#edf0ed] px-4 py-3">
                <h2 class="text-[15px] font-semibold text-acc-tx">
                    {{ $editingSiteId ? 'Редагувати сайт' : 'Новий сайт' }}
                </h2>
                <button type="button" wire:click="closePanel" class="text-mut hover:text-ink shrink-0" aria-label="Закрити">@svg('x')</button>
            </div>

            <form wire:submit="saveSite" class="space-y-4 px-4 py-4">
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Назва</label>
                    <input wire:model="siteName" type="text"
                        class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                    @error('siteName')
                        <p class="mt-1 text-[12px] text-bad-tx">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Домен</label>
                    <input wire:model="siteDomain" type="text" placeholder="example.com"
                        class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                    @error('siteDomain')
                        <p class="mt-1 text-[12px] text-bad-tx">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Код країни (необов’язково)</label>
                    <input wire:model="siteCountryHint" type="text" maxlength="8" placeholder="UA"
                        class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                    @error('siteCountryHint')
                        <p class="mt-1 text-[12px] text-bad-tx">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Група</label>
                    <select wire:model="siteGroupId"
                        class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                        <option value="">Без групи</option>
                        @foreach($groupOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('siteGroupId')
                        <p class="mt-1 text-[12px] text-bad-tx">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Сайт-джерело</label>
                    <select wire:model="parentSiteId"
                        class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                        <option value="">Без сайта-джерела</option>
                        @foreach($siteOptions as $id => $domain)
                            @if((int) $id !== (int) $editingSiteId)
                                <option value="{{ $id }}">{{ $domain }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('parentSiteId')
                        <p class="mt-1 text-[12px] text-bad-tx">{{ $message }}</p>
                    @enderror
                </div>

                @if($editingSiteId && $parentSiteId && $this->hasNoData())
                    <div class="mt-2 bg-[#f0f4f1] border border-acc-bg rounded-lg p-2.5">
                        <button type="button" 
                            wire:click="cloneParentData" 
                            wire:confirm="Скопіювати всі дані з сайту-джерела на цей сайт? Це створить локальні копії для всіх значень."
                            class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-acc text-acc-tx bg-white hover:bg-acc-bg px-3 py-2 text-xs font-semibold">
                            Клонувати дані з джерела
                        </button>
                    </div>
                @endif

                <div class="flex gap-2 pt-1">
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-acc px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                        Зберегти
                    </button>
                    <button type="button" wire:click="closePanel"
                        class="rounded-lg border border-[#dfe3e0] px-3 py-2 text-xs font-semibold text-mut hover:border-acc hover:text-acc-tx">
                        Скасувати
                    </button>
                </div>
            </form>

            @if($editingSiteId && $tokenStatus)
                <div class="space-y-3 border-t border-[#edf0ed] px-4 py-4">
                    <div class="text-[11px] uppercase tracking-wide text-mut">Токен і зв’язок</div>

                    <div class="space-y-1 rounded-lg border border-[#dfe3e0] bg-[#f6f8f6] px-3 py-2 text-[12px] text-mut">
                        <div>
                            Стан:
                            @if($tokenStatus['hasActiveToken'])
                                <span class="font-semibold text-ok-tx">чинний токен</span>
                            @else
                                <span class="font-semibold text-bad-tx">токена немає</span>
                            @endif
                        </div>
                        <div>
                            Остання активність:
                            {{ $tokenStatus['lastSeenAt'] ? \Illuminate\Support\Carbon::parse($tokenStatus['lastSeenAt'])->diffForHumans() : '—' }}
                        </div>
                        <div>
                            Публікація:
                            {{ $tokenStatus['lastVersion'] ? 'Версія '.$tokenStatus['lastVersion'] : 'ще не публікувалось' }}
                        </div>
                        @if($tokenStatus['pingUrl'])
                            <div class="break-all">
                                Listener:
                                <code>{{ $tokenStatus['pingUrl'] }}</code>
                            </div>
                        @endif
                    </div>

                    @if($visibleToken)
                        <div class="rounded-lg border border-acc-bd bg-acc-bg px-3 py-2">
                            <div class="mb-1 text-[11px] font-semibold text-acc-tx">Ключ підключення плагіна (показуємо один раз):</div>
                            <textarea readonly rows="5"
                                class="block w-full resize-y select-all rounded-md border border-acc-bd bg-white px-2 py-1 font-mono text-[11px] leading-4 text-ink">{{ $visibleToken }}</textarea>
                            <p class="mt-1 text-[11px] text-mut">Вставте цей ключ у WordPress: DBManager → Налаштування. Сирий API token плагіну не передається.</p>
                        </div>
                    @endif

                    <div class="flex gap-2">
                        @if($tokenStatus['hasActiveToken'])
                            <button type="button" wire:click="rotateToken"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-acc px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                                @svg('key') Ротувати
                            </button>
                            <button type="button" wire:click="revokeToken"
                                wire:confirm="Відкликати всі чинні токени сайта? Публікація зупиниться, доки не видасте новий."
                                class="rounded-lg border border-[#cdb9b4] px-3 py-2 text-xs font-semibold text-[#a85c52] hover:bg-[#f3e7e4]">
                                Відкликати
                            </button>
                        @else
                            <button type="button" wire:click="issueToken"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-acc px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                                @svg('key') Видати токен
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </aside>
    @endif

    @if($canManageSites && $purgingSiteId)
        <div class="fixed inset-0 z-40 bg-[rgba(20,26,22,0.34)]" wire:click="closePurgeDialog"></div>
        <div class="fixed left-1/2 top-1/2 z-50 w-[420px] max-w-[calc(100vw-24px)] -translate-x-1/2 -translate-y-1/2 rounded-lg border border-[#dfe3e0] bg-white shadow-[-18px_0_40px_rgba(28,34,30,0.14)]"
            wire:click.stop>
            <div class="flex items-center justify-between border-b border-[#edf0ed] px-4 py-3">
                <h2 class="text-[15px] font-semibold text-acc-tx">Остаточне видалення</h2>
                <button type="button" wire:click="closePurgeDialog" class="text-mut hover:text-ink" aria-label="Закрити">@svg('x')</button>
            </div>
            <form wire:submit.prevent="purgeSite" class="space-y-4 px-4 py-4">
                <p class="text-[12px] leading-5 text-mut">
                    Введіть точну назву домену, щоб остаточно видалити цей архівований сайт.
                </p>
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Домен сайту</label>
                    <input wire:model="purgingSiteConfirmation" type="text" autofocus
                        class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                    @error('purgingSiteConfirmation')
                        <p class="mt-1 text-[12px] text-bad-tx">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-bad-tx/40 bg-bad-bg px-3 py-2 text-xs font-semibold text-bad-tx hover:opacity-90">
                        Видалити остаточно
                    </button>
                    <button type="button" wire:click="closePurgeDialog"
                        class="rounded-lg border border-[#dfe3e0] px-3 py-2 text-xs font-semibold text-mut hover:border-acc hover:text-acc-tx">
                        Скасувати
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
