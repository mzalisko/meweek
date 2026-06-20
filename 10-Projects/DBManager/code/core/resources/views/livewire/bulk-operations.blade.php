<x-slot name="breadcrumb">
    <div class="ml-2 flex items-center gap-3 text-xs">
        <span class="text-mut">/</span>
        <span class="font-semibold text-acc-tx">Масові операції</span>
    </div>
</x-slot>

<x-slot name="context">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-acc-bd bg-acc-bg px-[18px] py-2 text-xs text-acc-tx">
        <div class="flex min-w-0 flex-wrap items-center gap-3">
            @svg('search')
            <span>Цілей: <b>{{ $stats['matched'] }}</b></span>
            <span class="text-mut">·</span>
            <span>Сайтів у зміні: <b>{{ $stats['sites'] }}</b></span>
            <span class="text-mut">·</span>
            <span>Превʼю: <b>{{ $stats['preview'] }}</b></span>
        </div>
        @if($report)
            <div class="shrink-0 flex items-center gap-2 rounded-md border border-ok-tx/20 bg-ok-bg px-2 py-1 text-[11px] text-ok-tx">
                <span>Змінено {{ $report['changed'] }} записів на {{ $report['sites'] }} сайтах</span>
                <span class="text-ok-tx/40">·</span>
                <a href="{{ route('admin.audit', ['tab' => 'changes', 'q' => $report['batch']]) }}" class="font-bold hover:underline inline-flex items-center gap-0.5">
                    Лог аудиту @svg('arrow-right', 10)
                </a>
            </div>
        @endif
    </div>
</x-slot>

<div class="grid min-h-full grid-cols-1 gap-0 bg-canvas xl:grid-cols-[300px_minmax(520px,1fr)_340px]">
    <aside class="border-b border-[#e3e5e1] bg-white p-3.5 xl:border-b-0 xl:border-r">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h1 class="text-[15px] font-semibold text-acc-tx">Вибірка</h1>
            <button type="button" wire:click="selectFilteredSites"
                class="inline-flex items-center gap-1 rounded-lg border border-[#dfe3e0] px-2 py-1 text-[11px] font-semibold text-mut hover:border-acc hover:text-acc-tx">
                @svg('check', 14) Вибрати видимі
            </button>
        </div>

        <div class="space-y-3">
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Область дії</label>
                <div class="grid grid-cols-2 gap-1.5">
                    <button type="button" wire:click="$set('scope', 'all')"
                        @class([
                            'flex flex-col items-center justify-center rounded-lg border p-2 text-center transition-all',
                            'border-acc bg-acc-bg text-acc-tx font-semibold shadow-sm' => $scope === 'all',
                            'border-[#dfe3e0] bg-white text-mut hover:border-acc hover:text-ink' => $scope !== 'all',
                        ])>
                        @svg('globe', 15)
                        <span class="mt-1 text-[10px] leading-tight">Усі сайти</span>
                    </button>
                    <button type="button" wire:click="$set('scope', 'group')"
                        @class([
                            'flex flex-col items-center justify-center rounded-lg border p-2 text-center transition-all',
                            'border-acc bg-acc-bg text-acc-tx font-semibold shadow-sm' => $scope === 'group',
                            'border-[#dfe3e0] bg-white text-mut hover:border-acc hover:text-ink' => $scope !== 'group',
                        ])>
                        @svg('grid', 14)
                        <span class="mt-1 text-[10px] leading-tight">Група</span>
                    </button>
                    <button type="button" wire:click="$set('scope', 'selected')"
                        @class([
                            'flex flex-col items-center justify-center rounded-lg border p-2 text-center transition-all',
                            'border-acc bg-acc-bg text-acc-tx font-semibold shadow-sm' => $scope === 'selected',
                            'border-[#dfe3e0] bg-white text-mut hover:border-acc hover:text-ink' => $scope !== 'selected',
                        ])>
                        @svg('check', 14)
                        <span class="mt-1 text-[10px] leading-tight">Вибрані ({{ count($selectedSiteIds) }})</span>
                    </button>
                    <button type="button" wire:click="$set('scope', 'tree')"
                        @class([
                            'flex flex-col items-center justify-center rounded-lg border p-2 text-center transition-all',
                            'border-acc bg-acc-bg text-acc-tx font-semibold shadow-sm' => $scope === 'tree',
                            'border-[#dfe3e0] bg-white text-mut hover:border-acc hover:text-ink' => $scope !== 'tree',
                        ])>
                        @svg('link', 14)
                        <span class="mt-1 text-[10px] leading-tight">Ієрархія</span>
                    </button>
                </div>
            </div>

            @if($scope === 'group')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Група</label>
                    <select wire:model.live="groupId" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="">Оберіть групу</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($scope === 'tree')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Кореневий сайт</label>
                    <select wire:model.live="rootSiteId" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="">Оберіть сайт</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->domain }} [ID: {{ $site->id }}]</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Пошук сайту</label>
                <input wire:model.live.debounce.300ms="siteSearch" type="search"
                    class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none"
                    placeholder="domain або назва">
            </div>

            <div class="relative rounded-lg border border-[#dfe3e0] overflow-hidden">
                <div wire:loading.delay wire:target="scope, groupId, rootSiteId, siteSearch, toggleSite, selectFilteredSites, clearSiteSelection" 
                    class="absolute inset-0 bg-white/60 backdrop-blur-[1px] z-10 flex items-center justify-center">
                    <span class="text-[11px] text-mut animate-pulse font-semibold flex items-center gap-1.5 bg-white px-2.5 py-1 rounded-full border shadow-sm">
                        <span class="h-2.5 w-2.5 animate-spin rounded-full border border-mut border-t-transparent"></span>
                        Оновлення...
                    </span>
                </div>
                <div class="max-h-[32vh] overflow-y-auto">
                    @forelse($siteOptions as $site)
                        @php $checked = in_array((int) $site->id, array_map('intval', $selectedSiteIds), true); @endphp
                        <button type="button" wire:click="toggleSite({{ $site->id }})"
                            class="flex w-full items-center gap-2 border-b border-[#edf0ed] px-2.5 py-2 text-left text-[11px] last:border-b-0 hover:bg-[#f6f8f6]">
                            <span @class([
                                'inline-flex h-4 w-4 shrink-0 items-center justify-center rounded border',
                                'border-acc bg-acc text-white' => $checked,
                                'border-[#c8cec9] bg-white' => !$checked,
                            ])>@if($checked) @svg('check', 12) @endif</span>
                            <span class="min-w-0">
                                <span class="block truncate font-mono text-ink">{{ $site->domain }}</span>
                                <span class="block truncate text-mut">{{ $site->group?->name ?? 'Без групи' }} · ID {{ $site->id }}</span>
                            </span>
                        </button>
                    @empty
                        <div class="px-3 py-4 text-center text-xs text-mut">Немає доступних сайтів.</div>
                    @endforelse
                </div>
            </div>

            <button type="button" wire:click="clearSiteSelection"
                class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-mut hover:text-acc-tx">
                @svg('x', 14) Очистити вибірку
            </button>
        </div>
    </aside>

    <main class="min-w-0 p-3.5">
        <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-5">
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Тип</label>
                <select wire:model.live="targetType" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    <option value="all">Усі типи (тільки основні)</option>
                    <option value="phone">Телефони (основні)</option>
                    <option value="phone_reserve">Резервні телефони</option>
                    <option value="messenger">Месенджери</option>
                    <option value="price">Ціни</option>
                    <option value="text">Текст</option>
                    <option value="address">Адреси</option>
                    <option value="social">Соцмережі</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Стан</label>
                <select wire:model.live="stateFilter" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    <option value="">Будь-який</option>
                    <option value="active">Активний</option>
                    <option value="hidden">Прихований</option>
                    <option value="down">Збій</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Гео</label>
                <select wire:model.live="geoFilter" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    <option value="">Будь-яке</option>
                    <option value="WORLD">WORLD</option>
                    @foreach($geoTags as $tag)
                        <option value="{{ $tag->code }}">{{ $tag->code }} · {{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Пошук</label>
                <input wire:model.live.debounce.300ms="search" type="search"
                    class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none"
                    placeholder="key, value, url">
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Номер</label>
                <input wire:model.live.debounce.300ms="phoneFilter" type="search"
                    class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none"
                    placeholder="+380 або фрагмент">
            </div>
        </div>

        @if($targetType !== 'all' || $stateFilter !== '' || $geoFilter !== '' || $search !== '' || $phoneFilter !== '')
            <div class="mb-3 flex items-center justify-between gap-2 bg-[#f6f8f6] border border-[#dfe3e0] rounded-lg px-3 py-1.5">
                <span class="text-[11px] text-mut flex items-center gap-1">
                    @svg('info', 13) Застосовано active-фільтри цілей
                </span>
                <button type="button" wire:click="resetFilters"
                    class="inline-flex items-center gap-1 rounded-md border border-bad-tx/20 bg-bad-bg/5 px-2 py-0.5 text-[11px] font-semibold text-bad-tx hover:bg-bad-bg/15 transition-colors">
                    @svg('x', 12) Скинути всі фільтри
                </button>
            </div>
        @endif
 
        <div class="overflow-x-auto rounded-lg border border-[#dfe3e0] bg-white relative">
            <div wire:loading.delay wire:target="targetType, stateFilter, geoFilter, search, phoneFilter, scope, groupId, rootSiteId, selectedSiteIds, operation, findText, replaceText, contentValue, statusValue, geoCodes, geoMode, phoneReplacement, phoneStatus, phoneFormat" 
                class="abs            <div class="min-w-[980px]">
                @php
                    $gridCols = match($targetType) {
                        'phone_reserve' => '1.5fr 1.2fr 1.2fr 1.5fr 2.0fr',
                        'phone' => '1.5fr 1.2fr 1.2fr 1.2fr 1.5fr 2.0fr 1.5fr',
                        default => '1.5fr 1.2fr 1.2fr 1.2fr 1.5fr 2.0fr',
                    };
                @endphp
                <div style="display: grid; grid-template-columns: {{ $gridCols }}; gap: 0.5rem;" class="border-b border-[#dfe3e0] bg-[#f6f8f6] px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-mut">
                    <div>Сайт</div>
                    <div>Група</div>
                    <div>Тип</div>
                    @if($targetType !== 'phone_reserve')
                        <div>Гео</div>
                    @endif
                    <div>Ключ</div>
                    <div>Поточне значення</div>
                    @if($targetType === 'phone')
                        <div>Форматування</div>
                    @endif
                </div>

                <div class="max-h-[calc(100vh-245px)] overflow-y-auto">
                    @forelse($previewRows as $row)
                        <div style="display: grid; grid-template-columns: {{ $gridCols }}; gap: 0.5rem;" class="border-b border-[#edf0ed] px-3 py-3 text-[13px] last:border-b-0 hover:bg-[#fafbfa]">
                            {{-- 1. Сайт --}}
                            <div class="min-w-0 font-mono text-ink text-[13px]">
                                <span class="block truncate">{{ $row['site'] }}</span>
                                <span class="text-[11px] text-mut">{{ in_array($row['type'], ['phone', 'phone_reserve'], true) ? 'номер' : 'значення' }}</span>
                            </div>
                            
                            {{-- 2. Група --}}
                            <div class="min-w-0 truncate text-mut text-[13px]">{{ $row['group'] }}</div>
                            
                            {{-- 3. Тип --}}
                            <div>
                                <span class="inline-flex items-center gap-1 rounded-md bg-[#eef1ee] px-1.5 py-0.5 text-[10px] font-semibold text-acc-tx">
                                    @if($row['type'] === 'phone')
                                        @svg('phone', 10)
                                    @elseif($row['type'] === 'phone_reserve')
                                        @svg('phone', 10)
                                    @elseif($row['type'] === 'messenger')
                                        @svg('msg', 10)
                                    @elseif($row['type'] === 'price')
                                        @svg('tag', 10)
                                    @elseif($row['type'] === 'text')
                                        @svg('text', 10)
                                    @elseif($row['type'] === 'address')
                                        @svg('pin', 10)
                                    @elseif($row['type'] === 'social')
                                        @svg('link', 10)
                                    @else
                                        @svg('alert', 10)
                                    @endif
                                    {{ $row['type'] === 'phone_reserve' ? 'резерв' : $row['type'] }}
                                </span>
                                
                                @if($row['changed'] && $row['state'] !== $row['new_state'])
                                    <span class="mt-1 block text-[10px] leading-tight font-semibold">
                                        <span class="line-through text-bad-tx">{{ $row['state'] }}</span>
                                        <span class="text-mut">→</span>
                                        <span class="text-ok-tx">{{ $row['new_state'] }}</span>
                                    </span>
                                @else
                                    <span class="mt-1 block text-[10px] text-mut">{{ $row['state'] }}</span>
                                @endif
                            </div>

                            {{-- 4. Гео (колонки немає тільки для резервних телефонів) --}}
                            @if($targetType !== 'phone_reserve')
                                <div class="flex min-w-0 flex-wrap gap-1 items-center">
                                    @if($row['type'] === 'phone_reserve')
                                        <span class="text-mut">—</span>
                                    @elseif($row['changed'] && $row['geo'] !== $row['new_geo'])
                                        <span class="text-mut line-through flex flex-wrap gap-1 opacity-60">
                                            @foreach($row['geo'] as $geo)
                                                <span class="text-[8px]">{{ $geo }}</span>
                                            @endforeach
                                        </span>
                                        <span class="text-mut text-[8px] select-none">→</span>
                                        <span class="flex flex-wrap gap-1">
                                            @foreach($row['new_geo'] as $geo)
                                                <span @class([
                                                    'rounded-md px-1.5 py-0.5 text-[10px] font-semibold leading-none whitespace-nowrap',
                                                    'bg-bad-bg text-bad-tx' => str_starts_with($geo, '!'),
                                                    'bg-ok-bg text-ok-tx font-bold' => !in_array($geo, $row['geo'], true),
                                                    'bg-[#eef1ee] text-mut' => in_array($geo, $row['geo'], true) && !str_starts_with($geo, '!'),
                                                ])>{{ $geo }}</span>
                                            @endforeach
                                        </span>
                                    @else
                                        @foreach($row['geo'] as $geo)
                                            <span @class([
                                                'rounded-md px-1.5 py-0.5 text-[10px] font-semibold leading-none whitespace-nowrap',
                                                'bg-bad-bg text-bad-tx' => str_starts_with($geo, '!'),
                                                'bg-[#eef1ee] text-mut' => !str_starts_with($geo, '!'),
                                            ])>{{ $geo }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif

                            {{-- 5. Ключ --}}
                            <div class="min-w-0 font-mono text-[#3c5a42] text-[13px]" title="{{ $row['key'] }}">
                                @if($row['changed'] && isset($row['new_key']) && $row['key'] !== $row['new_key'])
                                    <span class="line-through text-bad-tx block truncate">{{ $row['key'] }}</span>
                                    <span class="text-mut block text-[10px] leading-none my-0.5">▼</span>
                                    <span class="text-ok-tx font-bold bg-ok-bg/50 px-1 rounded block truncate text-[13px]">{{ $row['new_key'] }}</span>
                                @else
                                    <span class="block truncate">{{ $row['key'] }}</span>
                                @endif
                            </div>

                            {{-- 6. Поточне значення --}}
                            <div class="min-w-0 text-ink text-[13px]" title="{{ $row['value'] }}">
                                @php
                                    $showChangedLayout = $row['changed'] && $row['value'] !== $row['new_value'];
                                @endphp
                                @if($showChangedLayout)
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="line-through text-bad-tx opacity-70 text-[13px]">{{ $row['value'] }}</span>
                                    </div>
                                    <span class="text-mut text-[10px] block leading-none my-0.5">▼</span>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-[13px] rounded px-1.5 py-0.5 text-ok-tx font-semibold bg-ok-bg/60">{{ $row['new_value'] }}</span>
                                    </div>
                                @else
                                    <span class="text-[13px]">{{ $row['value'] }}</span>
                                @endif
                            </div>

                            {{-- 7. Форматування (тільки для регулярних телефонів) --}}
                            @if($targetType === 'phone')
                                <div class="min-w-0 text-ink text-[13px]">
                                    @if($row['type'] === 'phone')
                                        @php
                                            $hasFormatChange = ($row['format'] ?? '') !== ($row['new_format'] ?? '');
                                        @endphp
                                        @if($hasFormatChange)
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="line-through text-bad-tx opacity-70 font-mono text-[11px]">{{ $row['format'] ?: '—' }}</span>
                                                <span class="text-mut text-[10px] select-none">▼</span>
                                                <span class="text-ok-tx font-semibold bg-ok-bg/60 font-mono text-[11px] px-1.5 py-[0.5px] rounded">{{ $row['new_format'] ?: '—' }}</span>
                                            </div>
                                        @else
                                            <span class="text-[11px] text-mut bg-acc-bg border border-acc-bd px-1.5 py-[0.5px] rounded font-mono select-none">{{ $row['format'] ?: '—' }}</span>
                                        @endif
                                    @else
                                        <span class="text-mut">—</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <div class="text-sm font-semibold text-ink">Немає цілей для зміни</div>
                            <div class="mt-1 text-xs text-mut">Змініть область, тип, geo, стан або пошук.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        @if($stats['limited'])
            <div class="mt-2 rounded-lg border border-[#dfe3e0] bg-white px-3 py-2 text-[11px] text-mut">
                Показано перші {{ $stats['preview'] }} цілей із {{ $stats['matched'] }}. Операція застосовується до всієї поточної вибірки.
            </div>
        @endif
    </main>

    <aside class="border-t border-[#e3e5e1] bg-white p-3.5 xl:border-l xl:border-t-0">
        <h2 class="mb-3 text-[15px] font-semibold text-acc-tx">Операція</h2>

        <div class="space-y-3">
            @php
                $replacePhoneWithoutFilter = $operation === 'replace_phone' && trim($phoneFilter) === '';
                $applyDisabled = $stats['matched'] === 0 || $replacePhoneWithoutFilter;
            @endphp

            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-lg border border-[#dfe3e0] bg-[#f6f8f6] px-2.5 py-2">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-mut">Цілі</div>
                    <div class="mt-1 text-[18px] font-semibold text-ink">{{ $stats['matched'] }}</div>
                </div>
                <div class="rounded-lg border border-[#dfe3e0] bg-[#f6f8f6] px-2.5 py-2">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-mut">Сайти</div>
                    <div class="mt-1 text-[18px] font-semibold text-ink">{{ $stats['sites'] }}</div>
                </div>
                <div class="rounded-lg border border-[#dfe3e0] bg-[#f6f8f6] px-2.5 py-2">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-mut">Номери</div>
                    <div class="mt-1 text-[18px] font-semibold text-ink">{{ $stats['phones'] }}</div>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Дія</label>
                <select wire:model.live="operation" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    @if($targetType === 'phone_reserve')
                        <option value="replace_phone">Замінити номер</option>
                        <option value="set_phone_status">Стан номера active/down (Збій)</option>
                    @elseif($targetType === 'address')
                        <option value="set_geo">Змінити geo-теги</option>
                        <option value="set_status">Показати / приховати слот</option>
                    @else
                        <option value="replace_text">Знайти й замінити в key/content</option>
                        <option value="set_value">Встановити content.value</option>
                        <option value="set_geo">Змінити geo-теги</option>
                        <option value="set_status">Показати / приховати слот</option>
                        <option value="replace_phone">Замінити номер</option>
                        <option value="set_phone_status">Стан номера active/down (Збій)</option>
                        <option value="set_phone_format">Встановити формат телефону</option>
                    @endif
                </select>
                @error('operation') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
            </div>

            @if($operation === 'replace_text')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Знайти</label>
                    <input wire:model.live="findText" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    @error('findText') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Замінити на</label>
                    <input wire:model.live="replaceText" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                </div>
            @elseif($operation === 'set_value')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Нове значення</label>
                    <textarea wire:model.live="contentValue" rows="4" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none"></textarea>
                </div>
            @elseif($operation === 'set_status')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Новий стан слота</label>
                    <select wire:model.live="statusValue" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="active">active</option>
                        <option value="hidden">hidden</option>
                    </select>
                </div>
            @elseif($operation === 'set_geo')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Режим geo</label>
                    <select wire:model.live="geoMode" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="replace">Замінити набір</option>
                        <option value="add">Додати до наявних</option>
                        <option value="remove">Прибрати з наявних</option>
                    </select>
                </div>
                <div class="max-h-[30vh] overflow-y-auto rounded-lg border border-[#dfe3e0] p-2">
                    @foreach($geoTags as $tag)
                        <label class="flex items-center gap-2 rounded-md px-1.5 py-1 text-[12px] hover:bg-[#f6f8f6]">
                            <input wire:model.live="geoCodes" type="checkbox" value="{{ $tag->code }}" class="rounded border-[#c8cec9] text-acc focus:ring-acc">
                            <span class="font-mono">{{ $tag->code }}</span>
                            <span class="min-w-0 truncate text-mut">{{ $tag->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('geoCodes') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
            @elseif($operation === 'replace_phone')
                <div @class([
                    'rounded-lg border px-3 py-2 text-[12px]',
                    'border-bad-tx/20 bg-bad-bg text-bad-tx' => $replacePhoneWithoutFilter,
                    'border-[#dfe3e0] bg-[#f6f8f6] text-mut' => !$replacePhoneWithoutFilter,
                ])>
                    Фільтр “Номер” вище визначає, які записи замінювати.
                </div>
                @error('phoneFilter') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
                    <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Новий номер</label>
                    <input wire:model.live="phoneReplacement" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none" placeholder="+380...">
                    @error('phoneReplacement') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
                </div>
            @elseif($operation === 'set_phone_status')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Стан номера</label>
                    <select wire:model.live="phoneStatus" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="active">active</option>
                        <option value="down">down (Збій)</option>
                    </select>
                </div>
            @elseif($operation === 'set_phone_format')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Формат телефону</label>
                    <input wire:model.live="phoneFormat" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none" placeholder="+### (##) ###-##-##">
                    <p class="mt-1 text-[10px] text-mut">Використовуйте # для цифр і роздільники (пробіл, +, -, (), крапка). Залиште порожнім для видалення формату.</p>
                    @error('phoneFormat') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
                </div>
            @endif

            <label class="flex items-center gap-2 rounded-lg border border-[#dfe3e0] px-3 py-2 text-[12px] text-ink">
                <input wire:model.live="publishAfterApply" type="checkbox" class="rounded border-[#c8cec9] text-acc focus:ring-acc">
                <span>Публікувати сайти після зміни</span>
            </label>

            <button type="button" wire:click="apply"
                wire:confirm="Застосувати масову операцію до поточної вибірки?"
                wire:loading.attr="disabled"
                @disabled($applyDisabled)
                @class([
                    'inline-flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-xs font-bold transition-all duration-150',
                    'bg-acc text-white hover:bg-acc/90 active:scale-[0.98]' => !$applyDisabled,
                    'cursor-not-allowed bg-[#dfe3e0] text-mut' => $applyDisabled,
                ])>
                <span wire:loading.remove wire:target="apply" class="flex items-center gap-1.5">
                    @svg('check', 14) Застосувати до {{ $stats['matched'] }} цілей
                </span>
                <span wire:loading wire:target="apply" class="flex items-center gap-1.5">
                    <span class="h-3.5 w-3.5 animate-spin rounded-full border border-white border-t-transparent"></span>
                    Застосування...
                </span>
            </button>

            @if($report)
                <div class="rounded-lg border border-ok-tx/20 bg-ok-bg p-3 text-[12px] text-ok-tx space-y-2.5 shadow-sm">
                    <div class="font-bold flex items-center gap-1.5 text-ok-tx">
                        @svg('check-circle', 14) Операцію успішно виконано!
                    </div>
                    <div class="text-[11px] text-ok-tx/90 space-y-1">
                        <div>UUID сесії (Batch):</div>
                        <div class="flex items-center gap-1">
                            <code class="bg-white/70 px-1.5 py-0.5 rounded font-mono select-all shrink-0 text-[10px] text-ok-tx border border-ok-tx/10">{{ $report['batch'] }}</code>
                            <button type="button" onclick="navigator.clipboard.writeText('{{ $report['batch'] }}'); alert('UUID сесії скопійовано!');" 
                                class="p-1 hover:bg-white/40 rounded transition-colors text-ok-tx" title="Скопіювати UUID">
                                @svg('copy', 12)
                            </button>
                        </div>
                    </div>
                    <div class="pt-2 border-t border-ok-tx/10">
                        <a href="{{ route('admin.audit', ['tab' => 'changes', 'q' => $report['batch']]) }}" 
                            class="inline-flex items-center gap-1 text-[11px] font-bold text-ok-tx hover:underline">
                            @svg('eye', 12) Переглянути в логах аудиту
                        </a>
                    </div>
                </div>
            @endif

            <div class="mt-4 border-t border-[#edf0ed] pt-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-mut mb-2">Останні масові зміни</h3>
                @php
                    $recentSessions = $this->getRecentBulkSessions();
                @endphp
                @if($recentSessions->isEmpty())
                    <p class="text-[11px] text-mut">Немає недавніх масових операцій.</p>
                @else
                    <div class="space-y-2">
                        @foreach($recentSessions as $session)
                            <div class="rounded-lg border border-[#dfe3e0] bg-[#f6f8f6] p-2 text-[11px] flex flex-col gap-1">
                                <div class="flex justify-between items-start">
                                    <span class="font-semibold text-ink">
                                        {{ match($session['action']) {
                                            'bulk.replace_text' => 'Пошук і заміна',
                                            'bulk.set_value' => 'Встановлення значення',
                                            'bulk.set_status' => 'Зміна стану слота',
                                            'bulk.set_geo' => 'Зміна гео-тегів',
                                            'bulk.replace_phone' => 'Заміна номера',
                                            'bulk.set_phone_status' => 'Стан номера',
                                            'bulk.set_phone_format' => 'Формат телефону',
                                            default => $session['action']
                                        } }}
                                    </span>
                                    <span class="text-[10px] text-mut">{{ $session['created_at']?->format('H:i:s d.m') }}</span>
                                </div>
                                <div class="text-[10px] text-mut">
                                    Змінено записів: <span class="font-semibold text-ink">{{ $session['count'] }}</span>
                                </div>
                                
                                <details open class="mt-1 group text-[10px]">
                                    <summary class="text-acc-tx hover:underline cursor-pointer select-none font-semibold flex items-center gap-0.5 outline-none">
                                        Деталі змін ({{ $session['details']->count() }}):
                                    </summary>
                                    <div class="mt-1.5 space-y-1 pl-1.5 border-l border-acc/20 max-h-[120px] overflow-y-auto font-mono text-[9px] text-mut">
                                        @foreach($session['details'] as $det)
                                            <div class="leading-tight">
                                                <span class="text-ink font-semibold">{{ $det['site'] }}</span>: 
                                                <span class="text-[#3c5a42] font-semibold">{{ $det['key'] }}</span> 
                                                <span>({{ $det['change'] }})</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>

                                <button type="button" wire:click="rollbackBatch('{{ $session['batch_id'] }}')"
                                    wire:confirm="Скасувати цю сесію масових змін?"
                                    class="mt-1.5 self-start inline-flex items-center gap-1 rounded bg-bad-bg/10 border border-bad-tx/20 px-2 py-0.5 text-[10px] font-bold text-bad-tx hover:bg-bad-bg/20 transition-colors">
                                    @svg('trash', 10) Скасувати зміни
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </aside>
</div>
