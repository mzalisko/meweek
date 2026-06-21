<x-slot name="breadcrumb">
    <div class="flex items-center gap-3 ml-2 flex-wrap">
        <span class="text-mut text-sm select-none">/</span>
        <div class="inline-flex items-center bg-[#f4f5f3] px-3 py-1.5 rounded-lg border border-[#e3e5e1] text-xs font-bold text-ink select-none">
            Дашборд
        </div>
    </div>
</x-slot>

<x-slot name="context">
    <div class="bg-acc-bg border-b border-acc-bd px-[18px] py-2 text-xs text-acc-tx flex gap-3 items-center">
        @svg('grid')
        <span>Загальний огляд системи: статистика підключень, інциденти та улюблені.</span>
    </div>
</x-slot>

<div class="relative h-full w-full p-4 space-y-6">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[18px] font-semibold text-acc-tx">Дашборд</h1>
            <p class="mt-0.5 text-[12px] text-mut">Головний огляд стану підключених сайтів, активних інцидентів та вибраних ресурсів.</p>
        </div>
    </div>

    {{-- 1. Сітка статистики --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        
        {{-- Всього груп --}}
        <div class="bg-white border border-[#dfe3e0] rounded-lg p-4 shadow-sm hover:shadow transition-shadow flex items-center gap-3.5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-acc-bd bg-acc-bg text-acc-tx shrink-0">
                @svg('grid', 20)
            </span>
            <div>
                <div class="text-[10px] uppercase tracking-wider text-mut font-medium">Групи сайтів</div>
                <div class="text-[20px] font-bold text-ink leading-none mt-1">{{ $totalGroups }}</div>
            </div>
        </div>

        {{-- Всього сайтів --}}
        <div class="bg-white border border-[#dfe3e0] rounded-lg p-4 shadow-sm hover:shadow transition-shadow flex items-center gap-3.5">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-acc-bd bg-acc-bg text-acc-tx shrink-0">
                @svg('sites', 20)
            </span>
            <div>
                <div class="text-[10px] uppercase tracking-wider text-mut font-medium">Активні сайти</div>
                <div class="text-[20px] font-bold text-ink leading-none mt-1">{{ $totalSites }}</div>
            </div>
        </div>

        {{-- Втрачено зв'язок --}}
        <div @class([
            'border rounded-lg p-4 shadow-sm hover:shadow transition-shadow flex items-center gap-3.5',
            'bg-white border-[#dfe3e0]' => $totalOfflineSites === 0,
            'bg-bad-bg/30 border-bad-tx/20 text-bad-tx' => $totalOfflineSites > 0
        ])>
            <span @class([
                'inline-flex h-10 w-10 items-center justify-center rounded-lg border shrink-0',
                'border-acc-bd bg-acc-bg text-acc-tx' => $totalOfflineSites === 0,
                'border-bad-tx/30 bg-bad-bg text-bad-tx' => $totalOfflineSites > 0
            ])>
                @svg('globe', 20)
            </span>
            <div>
                <div class="text-[10px] uppercase tracking-wider text-mut font-medium">Втрачено зв'язок</div>
                <div class="text-[20px] font-bold leading-none mt-1 @if($totalOfflineSites > 0) text-bad-tx @else text-ink @endif">
                    {{ $totalOfflineSites }}
                </div>
            </div>
        </div>

        {{-- Active Incidents --}}
        <div @class([
            'border rounded-lg p-4 shadow-sm hover:shadow transition-shadow flex items-center gap-3.5',
            'bg-white border-[#dfe3e0]' => $activeIncidentsCount === 0,
            'bg-warn-bg/30 border-warn-tx/20 text-warn-tx' => $activeIncidentsCount > 0
        ])>
            <span @class([
                'inline-flex h-10 w-10 items-center justify-center rounded-lg border shrink-0',
                'border-acc-bd bg-acc-bg text-acc-tx' => $activeIncidentsCount === 0,
                'border-warn-tx/30 bg-warn-bg text-warn-tx' => $activeIncidentsCount > 0
            ])>
                @svg('alert', 20)
            </span>
            <div>
                <div class="text-[10px] uppercase tracking-wider text-mut font-medium">Активні інциденти</div>
                <div class="text-[20px] font-bold leading-none mt-1 @if($activeIncidentsCount > 0) text-warn-tx @else text-ink @endif">
                    {{ $activeIncidentsCount }}
                </div>
            </div>
        </div>

    </div>

    {{-- 2. Головний робочий простір --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        {{-- ЛІВА КОЛОНКА: Улюблені (7 cols) --}}
        <div class="lg:col-span-7 space-y-6">

            {{-- БЛОК УЛЮБЛЕНИХ --}}
            <div class="bg-white border border-[#dfe3e0] rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-[#f6f8f6] border-b border-[#dfe3e0] flex items-center justify-between">
                    <h2 class="text-xs uppercase tracking-wider text-ink font-bold flex items-center gap-1.5">
                        <span class="text-yellow-500 text-[14px]">★</span> Улюблені сайти та групи
                    </h2>
                </div>

                <div class="p-4 space-y-4">
                    @if($favoriteGroups->isEmpty() && $favoriteSites->isEmpty())
                        <div class="text-center py-8 text-mut">
                            <span class="text-[32px] block mb-2 opacity-50">⭐</span>
                            <p class="text-xs">У вас немає вибраних сайтів чи груп.</p>
                            <p class="text-[11px] text-mut/70 mt-1">Додайте їх на сторінці керування сайтами.</p>
                        </div>
                    @else
                        {{-- Улюблені групи --}}
                        @if($favoriteGroups->isNotEmpty())
                            <div class="space-y-3">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-mut">Групи</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach($favoriteGroups as $group)
                                        <div class="border border-[#dfe3e0] rounded-lg p-3 bg-[#fafbfa] relative group">
                                            <button wire:click="toggleFavorite('group', {{ $group->id }})" 
                                                class="absolute top-2.5 right-2.5 text-yellow-500 hover:text-gray-300 text-[14px]" title="Видалити з улюблених">
                                                ★
                                            </button>
                                            <div class="font-bold text-ink pr-6 mb-2">{{ $group->name }}</div>
                                            
                                            <div class="space-y-1.5 text-[11px]">
                                                @forelse($group->sites_with_status as $siteData)
                                                    @php 
                                                        $sModel = $siteData['model'];
                                                        $sStatus = $siteData['status'];
                                                    @endphp
                                                    <div class="flex items-center justify-between">
                                                        <a href="{{ route('admin.site', ['group' => $group->id, 'site' => $sModel->id]) }}" 
                                                            class="font-mono text-acc-tx hover:underline truncate max-w-[150px]">{{ $sModel->domain }}</a>
                                                        
                                                        <div class="flex items-center gap-1 shrink-0">
                                                            {{-- Лампочка зв'язку --}}
                                                            @if($sStatus['isOnline'])
                                                                <span class="inline-block h-2 w-2 rounded-full bg-ok-tx" title="Онлайн"></span>
                                                            @else
                                                                <span class="inline-block h-2 w-2 rounded-full bg-bad-tx animate-pulse" title="Поза мережею"></span>
                                                            @endif

                                                            {{-- Бейдж резервів --}}
                                                            @if($sStatus['hasExhausted'])
                                                                <span class="bg-bad-bg text-bad-tx font-bold text-[9px] px-1 rounded">EXHAUSTED</span>
                                                            @elseif($sStatus['hasReserve'])
                                                                <span class="bg-warn-bg text-warn-tx font-bold text-[9px] px-1 rounded">RESERVE</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @empty
                                                    <div class="text-mut italic">Сайти відсутні</div>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Улюблені сайти --}}
                        @if($favoriteSites->isNotEmpty())
                            <div class="space-y-3 @if($favoriteGroups->isNotEmpty()) pt-2 border-t border-[#edf0ed] @endif">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-mut">Сайти</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach($favoriteSites as $siteData)
                                        @php
                                            $site = $siteData['model'];
                                            $status = $siteData['status'];
                                        @endphp
                                        <div class="border border-[#dfe3e0] rounded-lg p-3 bg-white relative group flex flex-col justify-between">
                                            <button wire:click="toggleFavorite('site', {{ $site->id }})" 
                                                class="absolute top-2.5 right-2.5 text-yellow-500 hover:text-gray-300 text-[14px]" title="Видалити з улюблених">
                                                ★
                                            </button>
                                            <div class="pr-6">
                                                <a href="{{ route('admin.site', ['group' => $site->site_group_id, 'site' => $site->id]) }}" 
                                                    class="font-mono font-bold text-acc-tx hover:underline block truncate">{{ $site->domain }}</a>
                                                <div class="text-[10px] text-mut mt-0.5">Група: {{ $site->group?->name ?? '—' }}</div>
                                            </div>

                                            <div class="mt-3 flex items-center justify-between text-[11px] border-t border-[#edf0ed] pt-2">
                                                <span class="flex items-center gap-1">
                                                    @if($status['isOnline'])
                                                        <span class="inline-block h-2 w-2 rounded-full bg-ok-tx"></span>
                                                        <span class="text-ok-tx font-medium">В мережі</span>
                                                    @else
                                                        <span class="inline-block h-2 w-2 rounded-full bg-bad-tx animate-pulse"></span>
                                                        <span class="text-bad-tx font-medium">Поза мережею</span>
                                                    @endif
                                                </span>

                                                @if($status['hasExhausted'])
                                                    <span class="bg-bad-bg text-bad-tx font-bold text-[9px] px-1.5 py-0.5 rounded">EXHAUSTED</span>
                                                @elseif($status['hasReserve'])
                                                    <span class="bg-warn-bg text-warn-tx font-bold text-[9px] px-1.5 py-0.5 rounded">RESERVE</span>
                                                @else
                                                    <span class="bg-ok-bg text-ok-tx font-bold text-[9px] px-1.5 py-0.5 rounded">OK</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

        </div>

        {{-- ПРАВА КОЛОНКА: Інциденти + Офлайн (5 cols) --}}
        <div class="lg:col-span-5 space-y-6">
            
            {{-- БЛОК ІНЦИДЕНТІВ (ПОТРЕБУЄ УВАГИ) --}}
            <div id="incidents-section" class="bg-white border border-[#dfe3e0] rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-[#f6f8f6] border-b border-[#dfe3e0] flex items-center justify-between">
                    <h2 class="text-xs uppercase tracking-wider text-ink font-bold flex items-center gap-1.5">
                        ⚠️ Потребує уваги
                    </h2>
                    <span class="bg-warn-bg text-warn-tx text-[10px] px-2 py-0.5 rounded-full font-bold">
                        {{ $incidents->count() }} нових
                    </span>
                </div>

                <div class="p-4 space-y-3 max-h-[300px] overflow-y-auto">
                    @forelse($incidents as $inc)
                        @php $isCrit = $inc->severity === 'critical'; @endphp
                        <div @class([
                            'border rounded-lg p-3 bg-white shadow-sm flex items-start justify-between gap-2.5 transition-all duration-150 hover:bg-[#fafbfa]',
                            'border-l-4 border-l-[#96514a]' => $isCrit, // critical (red)
                            'border-l-4 border-l-[#96752f]' => !$isCrit // warning (orange)
                        ])>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span @class([
                                        'font-bold text-[9px] uppercase px-1.5 py-0.5 rounded leading-none',
                                        'bg-bad-bg text-bad-tx' => $isCrit,
                                        'bg-warn-bg text-warn-tx' => !$isCrit
                                    ])>
                                        {{ $isCrit ? 'Критично' : 'Увага' }}
                                    </span>
                                    <span class="text-[10px] text-mut">{{ $inc->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="text-[12px] text-ink font-medium mt-1.5 leading-snug">{{ $inc->message }}</div>
                            </div>
                            
                            <button wire:click="acknowledgeIncident({{ $inc->id }})" 
                                class="shrink-0 rounded border border-[#dfe3e0] bg-[#fafbfa] hover:border-ok-tx/50 hover:bg-ok-bg text-mut hover:text-ok-tx p-1 transition-colors"
                                title="Позначити як перевірений" aria-label="Підтвердити інцидент">
                                @svg('check')
                            </button>
                        </div>
                    @empty
                        <div class="text-center py-8 text-mut">
                            <span class="text-[28px] block mb-2">✅</span>
                            <p class="text-xs font-semibold text-ok-tx">Всі системи в штатному режимі</p>
                            <p class="text-[11px] text-mut/70 mt-0.5">Немає непідтверджених інцидентів.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- БЛОК ОФЛАЙН-САЙТІВ (ВТРАЧЕНО ЗВ'ЯЗОК) --}}
            <div class="bg-white border border-[#dfe3e0] rounded-lg shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-[#f6f8f6] border-b border-[#dfe3e0] flex items-center justify-between">
                    <h2 class="text-xs uppercase tracking-wider text-ink font-bold flex items-center gap-1.5">
                        📡 Втрачено зв'язок (Offline)
                    </h2>
                </div>

                <div class="p-4 space-y-3 max-h-[300px] overflow-y-auto">
                    @forelse($offlineSites as $site)
                        @php
                            $lastSeen = $site->tokens()->whereNull('revoked_at')->max('last_seen_at');
                        @endphp
                        <div class="flex items-center justify-between border border-bad-tx/10 bg-bad-bg/5 rounded-lg p-3 hover:bg-bad-bg/10 transition-colors">
                            <div class="min-w-0">
                                <a href="{{ route('admin.site', ['group' => $site->site_group_id, 'site' => $site->id]) }}" 
                                    class="font-mono font-bold text-bad-tx hover:underline block truncate">{{ $site->domain }}</a>
                                <div class="text-[10px] text-mut truncate mt-0.5">Група: {{ $site->group?->name ?? '—' }}</div>
                            </div>
                            
                            <div class="text-right shrink-0">
                                <span class="text-[11px] font-semibold text-bad-tx block">Офлайн</span>
                                <span class="text-[10px] text-mut">
                                    {{ $lastSeen ? Carbon\Carbon::parse($lastSeen)->diffForHumans() : 'ніколи не виходив' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-mut">
                            <span class="text-[28px] block mb-2">🟢</span>
                            <p class="text-xs font-semibold text-ok-tx">Усі сайти підключені</p>
                            <p class="text-[11px] text-mut/70 mt-0.5">Всі сайти успішно передають дані.</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

</div>
