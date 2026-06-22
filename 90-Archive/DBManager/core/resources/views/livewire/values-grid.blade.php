<x-slot name="breadcrumb">
    @php
        $currentGroupId = $selectedGroup?->id ?? $group ?? $siteModel?->group?->id;
        $currentGroupName = null;
        if ($currentGroupId) {
            $currentGroupName = $groups->firstWhere('id', $currentGroupId)?->name;
        }
    @endphp

    <div class="flex items-center gap-3 ml-2 flex-wrap">
        <span class="text-mut text-sm select-none">/</span>
        
        <!-- Кастомний селект ГРУПИ -->
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button data-site-group-select @click="open = !open" 
                class="flex items-center gap-2 bg-[#f4f5f3] hover:bg-[#ebede9] px-3 py-1.5 rounded-lg border border-[#e3e5e1] text-xs font-bold text-ink transition-colors outline-none focus:outline-none select-none">
                <span class="text-mut text-[10px] font-bold uppercase tracking-wider">Група</span>
                <span class="text-acc-tx font-bold">{{ $currentGroupName ?? 'Оберіть групу' }}</span>
                <svg class="w-3.5 h-3.5 text-mut transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
            
            <div x-show="open" 
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="transform opacity-0 scale-95"
                 x-transition:enter-end="transform opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="transform opacity-100 scale-100"
                 x-transition:leave-end="transform opacity-0 scale-95"
                 x-cloak
                 class="absolute left-0 mt-1.5 z-40 bg-white border border-[#dfe3e0] rounded-lg shadow-lg py-1.5 min-w-[210px] max-h-[300px] overflow-y-auto">
                <a href="{{ route('admin.site') }}"
                   class="flex items-center justify-between px-3.5 py-2 text-xs text-mut hover:bg-[#eef1ee] hover:text-acc-tx transition-colors {{ !$currentGroupId ? 'bg-[#eef1ee] text-acc-tx font-semibold' : '' }}">
                    <span>Оберіть групу</span>
                    @if(!$currentGroupId)
                        <svg class="w-3.5 h-3.5 text-acc-tx" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    @endif
                </a>
                @foreach($groups as $groupOption)
                    <a href="{{ route('admin.site', ['group' => $groupOption->id]) }}" data-group-id="{{ $groupOption->id }}"
                       class="flex items-center justify-between px-3.5 py-2 text-xs text-ink hover:bg-[#eef1ee] hover:text-acc-tx transition-colors {{ (int) $currentGroupId === (int) $groupOption->id ? 'bg-[#eef1ee] text-acc-tx font-semibold' : '' }}">
                        <span>{{ $groupOption->name }}</span>
                        @if((int) $currentGroupId === (int) $groupOption->id)
                            <svg class="w-3.5 h-3.5 text-acc-tx" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <span class="text-mut text-sm select-none">/</span>

        <!-- Кастомний селект САЙТУ -->
        <div class="relative {{ !$currentGroupId ? 'opacity-50 pointer-events-none' : '' }}" x-data="{ open: false }" @click.outside="open = false">
            <button data-site-select @click="open = !open" @disabled(!$currentGroupId)
                class="flex items-center gap-2 bg-[#f4f5f3] hover:bg-[#ebede9] px-3 py-1.5 rounded-lg border border-[#e3e5e1] text-xs font-bold text-ink transition-colors outline-none focus:outline-none disabled:cursor-not-allowed select-none">
                <span class="text-mut text-[10px] font-bold uppercase tracking-wider">Сайт</span>
                <span class="text-ink font-mono font-medium">{{ $siteModel ? ($siteModel->domain . ' [ID: ' . $siteModel->id . ']') : 'Оберіть сайт' }}</span>
                <svg class="w-3.5 h-3.5 text-mut transition-transform duration-200 shrink-0" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
            
            @if($currentGroupId)
                <div x-show="open" 
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     x-cloak
                     class="absolute left-0 mt-1.5 z-40 bg-white border border-[#dfe3e0] rounded-lg shadow-lg py-1.5 min-w-[210px] max-h-[300px] overflow-y-auto">
                    <a href="{{ route('admin.site', ['group' => $currentGroupId]) }}"
                       class="flex items-center justify-between px-3.5 py-2 text-xs text-mut hover:bg-[#eef1ee] hover:text-acc-tx transition-colors {{ !$siteModel ? 'bg-[#eef1ee] text-acc-tx font-semibold' : '' }}">
                        <span>Оберіть сайт</span>
                        @if(!$siteModel)
                            <svg class="w-3.5 h-3.5 text-acc-tx" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @endif
                    </a>
                    @foreach($selectedGroupSites->sortBy('domain') as $s)
                        <a href="{{ route('admin.site', ['group' => $s->site_group_id, 'site' => $s->id]) }}"
                           class="flex items-center justify-between px-3.5 py-2 text-xs text-ink hover:bg-[#eef1ee] hover:text-acc-tx font-mono transition-colors {{ (int) $siteModel?->id === (int) $s->id ? 'bg-[#eef1ee] text-acc-tx font-semibold' : '' }}">
                            <span>{{ $s->domain }} <span class="text-mut text-[10px] font-sans ml-1">ID: {{ $s->id }}</span></span>
                            @if((int) $siteModel?->id === (int) $s->id)
                                <svg class="w-3.5 h-3.5 text-acc-tx" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-slot>

<x-slot name="context">
    <div class="bg-acc-bg border-b border-acc-bd px-[18px] py-2 text-xs text-acc-tx flex gap-3 items-center">
        @svg('globe')
        <span>Сайт: <b>{{ $siteModel ? ($siteModel->domain . ' [ID: ' . $siteModel->id . ']') : '—' }}</b></span>
        @if($siteModel?->group)
            <span class="text-mut">·</span>
            <span>Група: {{ $siteModel->group->name }}</span>
        @endif
    </div>
</x-slot>

<div @if($editLockResource) wire:poll.15s="refreshEditLock" @endif class="flex gap-0 min-h-0" data-values-grid-root>
<div class="flex-1 min-w-0 p-3.5">
    {{-- Site context heading (also ensures domain is in the component's wire-snapshot for tests) --}}
    @if($siteModel)
        <div class="text-[11px] text-mut mb-2 hidden" aria-label="site-domain">{{ $siteModel->domain }}</div>
    @endif

    @if($siteModel)
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-[#dfe3e0] pb-3">
            <div class="flex min-w-0 items-center gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-acc-bd bg-acc-bg text-acc-tx">
                    @svg('globe')
                </span>
                <div class="min-w-0">
                    <div class="text-[10px] uppercase tracking-[.08em] text-mut">Поточний сайт</div>
                    <h1 class="truncate font-mono text-[18px] font-semibold leading-6 text-ink" title="{{ $siteModel->domain }}">{{ $siteModel->domain }} <span class="text-mut text-[12px] font-normal ml-2">ID: {{ $siteModel->id }}</span></h1>
                    @if($siteModel?->group)
                        <div class="truncate text-[11px] text-mut">Група: {{ $siteModel->group->name }}</div>
                    @endif
                </div>
            </div>
            @if($canEditCurrentSite || $canManageSites)
                <div class="flex items-center gap-2">
                    @if($canManageSites)
                        <button wire:click="editSite"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#c4c7c3] bg-white px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-acc-bg">
                            @svg('edit', 13)
                            Редагувати сайт
                        </button>
                    @endif
                    @if($canEditCurrentSite)
                        <button wire:click="syncCurrentSite" wire:loading.attr="disabled"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#c4c7c3] bg-white px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-acc-bg disabled:opacity-50">
                            @svg('refresh', 13)
                            Синхронізувати
                        </button>
                        <button wire:click="addValue"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-acc px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-acc/90">
                            + Додати значення
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @endif

    @include('livewire.partials.edit-lock-alert')

    @if(!$siteModel)
        <div class="rounded-lg border border-dashed border-[#dfe3e0] bg-white px-4 py-8 text-center">
            <div class="text-sm font-semibold text-ink">Оберіть сайт</div>
            <div class="mt-1 text-xs text-mut">Стартовий екран сайту відкривається після вибору домену у верхньому списку.</div>
        </div>
    @elseif(empty($rows))
        <div class="text-mut text-sm py-8 text-center">Значення відсутні</div>
    @else
        @php
            $typeLabels = [
                'phone'     => ['Телефони',    'phone'],
                'messenger' => ['Месенджери',  'msg'],
                'price'     => ['Ціни',        'tag'],
                'address'   => ['Адреси',      'pin'],
                'social'    => ['Соцмережі',   'link'],
                'text'      => ['Текст',       'tag'],
            ];
            $stateMap = [
                'ok'         => 'ok',
                'on_reserve' => 'warn',
                'pinned'     => 'warn',
                'exhausted'  => 'bad',
                'hidden'     => 'bad',
            ];
            $stateLabels = [
                'ok'         => 'ok',
                'on_reserve' => 'резерв',
                'pinned'     => 'закріплено',
                'exhausted'  => 'вичерпано',
                'hidden'     => 'приховано',
            ];
            $rowsCollection = collect($rows);
            $typeOrder = ['phone', 'messenger', 'price', 'address', 'social', 'text'];
            $orderedRows = collect($typeOrder)
                ->filter(fn ($orderedType) => $rowsCollection->has($orderedType))
                ->mapWithKeys(fn ($orderedType) => [$orderedType => $rowsCollection->get($orderedType)])
                ->merge($rowsCollection->except($typeOrder));
            $sectionTypes = $orderedRows->keys()->values()->all();
        @endphp

        <div
            x-data="{
                collapsed: {},
                sectionTypes: {{ json_encode($sectionTypes) }},
                isCollapsed(type) { return this.collapsed[type] === true },
                toggle(type) { this.collapsed[type] = !this.isCollapsed(type) },
                expandAll() { this.sectionTypes.forEach(type => this.collapsed[type] = false) },
                collapseAll() { this.sectionTypes.forEach(type => this.collapsed[type] = true) },
                collapsedReserves: { phone: true, messenger: true },
                isReservesCollapsed(type) { return this.collapsedReserves[type] === true },
                toggleReserves(type) { this.collapsedReserves[type] = !this.isReservesCollapsed(type) },
            }"
            class="space-y-3"
        >
            <div class="flex justify-end gap-1.5">
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

            @foreach($orderedRows as $type => $items)
                <div class="border border-[#dfe3e0] bg-white rounded-lg">
                    <div role="button" tabindex="0"
                        @click="toggle('{{ $type }}')"
                        @keydown.enter.prevent="toggle('{{ $type }}')"
                        @keydown.space.prevent="toggle('{{ $type }}')"
                        :aria-expanded="(!isCollapsed('{{ $type }}')).toString()"
                        class="grid w-full gap-2 items-center px-2.5 py-2 bg-[#f6f8f6] border-[#dfe3e0] rounded-t-lg text-left text-[10px] uppercase tracking-wide text-mut hover:bg-[#eef1ee] transition-colors cursor-pointer select-none"
                        style="grid-template-columns: minmax(130px, 1fr) minmax(150px, 1fr) 92px minmax(280px, 1.8fr) 140px 68px;"
                        :class="isCollapsed('{{ $type }}') ? 'rounded-b-lg border-b-0' : 'border-b'">
                        <div class="flex gap-1.5 items-center min-w-0">
                            <span class="inline-flex transition-transform duration-150" :class="isCollapsed('{{ $type }}') ? '-rotate-90' : ''">@svg('chevron-down')</span>
                            @svg($typeLabels[$type][1] ?? 'tag')
                            <span class="truncate">{{ $typeLabels[$type][0] ?? $type }}</span>
                            <span class="bg-[#eef1ee] border border-[#c8cec9] rounded-full px-2 py-0.5 text-[10px] leading-none">{{ count($items) }}</span>
                        </div>
                        <div>Гео</div>
                        <div>Стан</div>
                        <div>Значення</div>
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span>Форматування</span>
                            @if($type === 'phone' || $type === 'messenger')
                                <button type="button" @click.stop="toggleReserves('{{ $type }}')"
                                    class="inline-flex items-center gap-1 rounded-md border border-[#dfe3e0] bg-white px-1.5 py-0.5 leading-none text-mut hover:border-acc hover:text-acc-tx transition-colors"
                                    :title="isReservesCollapsed('{{ $type }}') ? 'Показати резерви' : 'Сховати резерви'"
                                    aria-label="Показати або сховати резерви">
                                    <span class="normal-case">резерви</span>
                                    <span class="inline-flex transition-transform duration-150" :class="isReservesCollapsed('{{ $type }}') ? '-rotate-90' : ''">@svg('chevron-down')</span>
                                </button>
                            @endif
                        </div>
                        <div></div>
                    </div>

                    <div x-show="!isCollapsed('{{ $type }}')" class="bg-[#f4f5f3]/20 p-2.5 space-y-2.5 rounded-b-lg">
                    @foreach($items as $r)
                    @php
                        $st = $stateMap[$r['state']] ?? 'ok';
                        $borderColors = [
                            'ok'   => 'border-l-[#52705f]',
                            'warn' => 'border-l-[#96752f]',
                            'bad'  => 'border-l-[#96514a]',
                        ];
                        $borderColor = $borderColors[$st] ?? 'border-l-mut';
                    @endphp
                    <div class="grid gap-2 items-start px-3.5 py-3 bg-white border border-[#dfe3e0] border-l-4 {{ $borderColor }} rounded-r-lg shadow-sm hover:shadow hover:bg-[#fafbfa] transition-all duration-150"
                        style="grid-template-columns: minmax(130px, 1fr) minmax(150px, 1fr) 92px minmax(280px, 1.8fr) 140px 68px;">
                        {{-- Key --}}
                        <div class="flex items-center gap-1 mt-0.5 min-w-0 select-none">
                            <span class="text-slate-400 font-semibold text-[13px] shrink-0">#</span>
                            <span class="font-mono text-xs font-bold text-slate-800 bg-slate-50 border border-slate-200 rounded-md px-2 py-1 select-all shadow-sm hover:bg-slate-100 transition-colors truncate" title="{{ $r['key'] }}">{{ $r['key'] }}</span>
                        </div>

                        {{-- Geo --}}
                        @if($type === 'price' && !empty($r['prices']))
                            <span class="min-w-0 flex flex-col gap-1 text-mut text-[11px]">
                                @foreach($r['prices'] as $price)
                                    @php
                                        $priceGeo = $price['geo'] ?? ['WORLD'];
                                        if (empty($priceGeo)) {
                                            $priceGeo = ['WORLD'];
                                        }
                                    @endphp
                                    <span class="flex min-h-[28px] min-w-0 flex-wrap items-center gap-1 px-2 py-1" title="{{ implode(', ', $priceGeo) }}">
                                        @foreach($priceGeo as $geo)
                                            @if(str_starts_with($geo, '!'))
                                                <span class="inline-flex max-w-full rounded-md bg-bad-bg text-bad-tx border border-bad-tx/20 px-1.5 py-0.5 leading-none font-semibold text-[10px]">{{ $geo }}</span>
                                            @else
                                                <span class="inline-flex max-w-full rounded-md bg-[#eef1ee] px-1.5 py-0.5 leading-none text-[10px]">{{ $geo }}</span>
                                            @endif
                                        @endforeach
                                    </span>
                                @endforeach
                            </span>
                        @else
                            <span class="min-w-0 flex flex-wrap gap-1 text-mut text-[11px] pt-1" title="{{ implode(', ', $r['geo']) }}">
                                @foreach($r['geo'] as $geo)
                                    @if(str_starts_with($geo, '!'))
                                        <span class="inline-flex max-w-full rounded-md bg-bad-bg text-bad-tx border border-bad-tx/20 px-1.5 py-0.5 leading-none font-semibold text-[10px]">{{ $geo }}</span>
                                    @else
                                        <span class="inline-flex max-w-full rounded-md bg-[#eef1ee] px-1.5 py-0.5 leading-none text-[10px]">{{ $geo }}</span>
                                    @endif
                                @endforeach
                            </span>
                        @endif

                        {{-- Status badge --}}
                        <span class="min-w-0">
                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-semibold text-[11px] bg-{{ $st }}-bg text-{{ $st }}-tx">
                                ● {{ $stateLabels[$r['state']] ?? $r['state'] }}
                            </span>
                        </span>

                        {{-- Value / inline phone edit --}}
                        <div class="min-w-0">
                            @if($type === 'phone' && !empty($r['numbers']))
                                @php
                                    $hasActivePhoneReserve = collect($r['numbers'])->contains(fn ($n) => $n['priority'] > 0 && ($n['is_current'] ?? false));
                                @endphp
                                <div class="flex flex-col gap-1">
                                    @foreach($r['numbers'] as $number)
                                        @php
                                            $isEditingNumber = $editingPhoneEntryId === $number['entry_id'];
                                            $numberStatus = $number['status'] ?? 'active';
                                            $isInactive = $numberStatus !== 'active';
                                            $isPinned = $number['is_pinned'] ?? false;
                                            $phoneRowVisible = $number['is_current'] ? 'true' : 'false';
                                        @endphp
                                        @if($isEditingNumber)
                                            <span
                                                x-data="{ initial: '{{ addslashes($number['e164']) }}' }"
                                                @click.outside="
                                                    const inp = $el.querySelector('input');
                                                    if (inp && inp.value.trim() === initial) {
                                                        $wire.cancelInlinePhoneEdit();
                                                    }
                                                "
                                                class="flex min-h-8 items-center gap-2 min-w-0 rounded-md px-2 py-1 {{ $number['is_current'] ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }} {{ $isInactive ? 'opacity-70' : '' }}"
                                                @if($number['priority'] > 0)
    x-show="!isReservesCollapsed('phone') || {{ $phoneRowVisible }}"
    x-cloak
@endif
                                            >
                                                <span class="w-24 shrink-0 text-[10px] uppercase tracking-wide {{ $number['is_current'] ? 'text-acc-tx font-semibold' : 'text-mut' }}">
                                                    {{ $number['priority'] === 0 ? '#1 основний' : '#1.' . $number['priority'] . ' резерв' }}
                                                </span>
                                                <input
                                                    wire:model.defer="editingPhoneNumber"
                                                    wire:keydown.enter="saveInlinePhoneNumber"
                                                    wire:keydown.escape="cancelInlinePhoneEdit"
                                                    type="text"
                                                    class="flex-1 min-w-0 border border-acc rounded-md px-2 py-1 text-xs text-ink focus:outline-none"
                                                    aria-label="Редагувати номер"
                                                >
                                                <span class="dbm-action-grid ml-auto shrink-0 grid grid-cols-[92px_24px_24px_24px_24px] items-center justify-items-center gap-1">
                                                    <button wire:click.stop="saveInlinePhoneNumber" class="text-ok-tx hover:opacity-80 px-1 py-0.5" title="Зберегти" aria-label="Зберегти">@svg('check')</button>
                                                    <button wire:click.stop="cancelInlinePhoneEdit" class="text-mut hover:text-ink px-1 py-0.5" title="Скасувати" aria-label="Скасувати">@svg('x')</button>
                                                    <button wire:click.stop="removeInlinePhoneNumber({{ $number['entry_id'] }})" wire:confirm="Видалити цей номер із ланцюга?" class="text-mut hover:text-bad-tx px-1 py-0.5" title="Видалити" aria-label="Видалити">@svg('trash')</button>
                                                </span>
                                            </span>
                                        @else
                                            <span class="flex min-h-8 items-center gap-2 min-w-0 rounded-md px-2 py-1 {{ $number['is_current'] ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }} {{ $isInactive ? 'opacity-70' : '' }}"
                                                @if($number['priority'] > 0)
    x-show="!isReservesCollapsed('phone') || {{ $phoneRowVisible }}"
    x-cloak
@endif
                                            >
                                                <span class="w-24 shrink-0 text-[10px] uppercase tracking-wide {{ $number['is_current'] ? 'text-acc-tx font-semibold' : 'text-mut' }}">
                                                    {{ $number['priority'] === 0 ? '#1 основний' : '#1.' . $number['priority'] . ' резерв' }}
                                                </span>
                                                <span class="min-w-0 flex-1 truncate text-ink font-mono text-[12px]" title="{{ $number['e164'] }}">
                                                    {{ $number['e164'] }}
                                                </span>
                                                <span class="dbm-action-grid ml-auto shrink-0 grid grid-cols-[92px_24px_24px_24px_24px] items-center justify-items-center gap-1">
                                                    @if($isInactive)
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-bad-bg px-1.5 py-0.5 text-[10px] font-semibold text-bad-tx">● неактивний</span>
                                                        <button wire:click.stop="restorePhoneNumber({{ $number['entry_id'] }})" class="text-ok-tx hover:opacity-80 px-1 py-0.5" title="Повернути" aria-label="Повернути">@svg('eye')</button>
                                                        <span class="inline-flex w-6 justify-center px-1 py-0.5 text-transparent opacity-0" aria-hidden="true">@svg('pin')</span>
                                                    @else
                                                        @if($number['is_current'])
                                                            <span class="inline-flex items-center gap-1 rounded-md bg-ok-bg px-1.5 py-0.5 text-[10px] font-semibold text-ok-tx">● показується</span>
                                                        @endif
                                                        @if($isPinned)
                                                            <button wire:click.stop="unpinPhoneSlot({{ $number['entry_id'] }})" class="text-acc-tx hover:opacity-80 px-1 py-0.5" title="Зняти ручний режим" aria-label="Зняти ручний режим">@svg('pin')</button>
                                                        @else
                                                            <button wire:click.stop="pinPhoneNumber({{ $number['entry_id'] }})" class="text-mut hover:text-acc-tx px-1 py-0.5" title="Показувати цей" aria-label="Показувати цей">@svg('pin')</button>
                                                        @endif
                                                        <button wire:click.stop="deactivatePhoneNumber({{ $number['entry_id'] }})" wire:confirm="Приховати номер і позначити його неактивним?" class="text-mut hover:text-bad-tx px-1 py-0.5" title="Приховати / деактивувати" aria-label="Приховати номер">@svg('ban')</button>
                                                    @endif
                                                    <span class="inline-flex w-6 justify-center px-1 py-0.5 text-transparent opacity-0" aria-hidden="true">@svg('link')</span>
                                                    <button wire:click.stop="startInlinePhoneEdit({{ $number['entry_id'] }})" class="text-mut hover:text-acc-tx px-1 py-0.5" title="Редагувати номер" aria-label="Редагувати номер">@svg('edit')</button>
                                                </span>
                                            </span>
                                        @endif
                                        @if($isEditingNumber)
                                            @error('editingPhoneNumber')
                                                <span class="ml-[5.5rem] text-[11px] text-bad-tx" @if($number['priority'] > 0) x-show="!isReservesCollapsed('phone') || {{ $phoneRowVisible }}" x-cloak @elseif($hasActivePhoneReserve) x-show="!isReservesCollapsed('phone')" x-cloak @endif>{{ $message }}</span>
                                            @enderror
                                        @endif
                                    @endforeach
                                    
                                    {{-- Inline add phone reserve input --}}
                                    <div class="mt-1.5 flex items-center gap-2 pl-2" x-show="!isReservesCollapsed('phone')">
                                        <input
                                            wire:model.defer="newPhoneValue.{{ $r['id'] }}"
                                            wire:keydown.enter="addPhoneReserve({{ $r['id'] }})"
                                            type="text"
                                            placeholder="+380... (додати резерв)"
                                            class="flex-1 min-w-0 border border-[#dfe3e0] rounded-md px-2 py-1 text-xs text-ink focus:outline-none focus:border-acc"
                                            aria-label="Додати резервний номер"
                                        >
                                        <button type="button" wire:click.stop="addPhoneReserve({{ $r['id'] }})"
                                            class="shrink-0 text-mut hover:text-acc-tx px-1 py-0.5" title="Додати резерв">@svg('plus')</button>
                                    </div>
                                    @error("newPhoneValue.{$r['id']}")
                                        <span class="text-[11px] text-bad-tx pl-2 mt-0.5 block" x-show="!isReservesCollapsed('phone')">{{ $message }}</span>
                                    @enderror

                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] text-mut pl-2" x-show="!isReservesCollapsed('phone')">
                                        <span>Якщо всі впали:</span>
                                        <button wire:click.stop="setPhoneExhaustionPolicy({{ $r['id'] }}, 'hide')"
                                            class="rounded-md px-1.5 py-0.5 border {{ ($r['exhaustion_policy'] ?? 'hide') === 'hide' ? 'bg-acc text-white border-acc' : 'border-[#dfe3e0] hover:border-acc' }}">прибрати</button>
                                        <button wire:click.stop="setPhoneExhaustionPolicy({{ $r['id'] }}, 'last')"
                                            class="rounded-md px-1.5 py-0.5 border {{ ($r['exhaustion_policy'] ?? 'hide') === 'last' ? 'bg-acc text-white border-acc' : 'border-[#dfe3e0] hover:border-acc' }}">останній</button>
                                        <button wire:click.stop="setPhoneExhaustionPolicy({{ $r['id'] }}, 'emergency')"
                                            class="rounded-md px-1.5 py-0.5 border {{ ($r['exhaustion_policy'] ?? 'hide') === 'emergency' ? 'bg-acc text-white border-acc' : 'border-[#dfe3e0] hover:border-acc' }}">аварійний</button>
                                        @if(($r['exhaustion_policy'] ?? 'hide') === 'emergency')
                                            <input
                                                type="text"
                                                value="{{ $r['emergency_number'] ?? '' }}"
                                                wire:change="savePhoneEmergencyNumber({{ $r['id'] }}, $event.target.value)"
                                                placeholder="аварійний номер"
                                                class="flex-1 min-w-[140px] border border-[#dfe3e0] rounded-md px-2 py-0.5 text-[11px] text-ink focus:outline-none focus:border-acc"
                                                aria-label="Аварійний номер">
                                        @endif
                                    </div>

                                    @if($r['state'] === 'exhausted' && in_array($r['exhaustion_policy'] ?? null, ['last', 'emergency'], true))
                                        <span class="inline-flex items-center gap-2 rounded-md px-2 py-1 text-[10px] font-semibold {{ $r['exhaustion_policy'] === 'emergency' ? 'bg-bad-bg text-bad-tx' : 'bg-warn-bg text-warn-tx' }}">
                                            {{ $r['exhaustion_policy'] === 'emergency' ? 'аварійний' : 'останній' }}
                                            <span class="font-mono text-ink">{{ $r['value'] ?? '—' }}</span>
                                        </span>
                                    @endif
                                    @if(!empty($r['messengers']))
                                        <div class="flex flex-wrap gap-1 mt-1.5 pl-2">
                                            @foreach($r['messengers'] as $lm)
                                                <button wire:click.stop="startInlineMessengerEdit({{ $lm['id'] }})"
                                                    class="inline-flex items-center gap-1 rounded-full border border-[#dfe3e0] bg-white px-2 py-0.5 text-[10px] text-mut hover:border-acc hover:text-acc-tx transition-colors">
                                                    @svg('msg')
                                                    {{ ucfirst($lm['network']) }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="flex flex-col gap-1 min-w-0">
                                    @if($r['state'] === 'exhausted' && in_array($r['exhaustion_policy'] ?? null, ['last', 'emergency'], true))
                                        <span class="shrink-0 rounded-md bg-bad-bg px-1.5 py-0.5 text-[10px] font-semibold text-bad-tx">
                                            {{ $r['exhaustion_policy'] === 'emergency' ? 'аварійний' : 'останній' }}
                                        </span>
                                    @endif
                                    @if($type === 'messenger')
                                        @php
                                            $isEditingMessenger = $editingMessengerId === $r['id'];
                                            $isInactiveMessenger = !($r['enabled'] ?? true);
                                            $hasActiveMessengerReserve = collect($r['reserve_rows'] ?? [])->contains(fn ($res) => ($res['is_current'] ?? false));
                                        @endphp
                                        <div class="flex min-h-8 items-center gap-2 min-w-0 rounded-md px-2 py-1 {{ ($r['is_current'] ?? false) ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }} {{ $isInactiveMessenger ? 'opacity-70' : '' }}">
                                            <span class="w-24 shrink-0 truncate text-[10px] uppercase tracking-wide {{ ($r['is_current'] ?? false) ? 'text-acc-tx font-semibold' : 'text-mut' }}">#1 {{ $r['network'] ?? 'msg' }}</span>
                                            @if($isEditingMessenger)
                                                <div x-data="{ initial: '{{ addslashes($r['value'] ?? '') }}' }"
                                                     @click.outside="
                                                        const inp = $el.querySelector('input');
                                                        if (inp && inp.value.trim() === initial) {
                                                            $wire.cancelInlineMessengerEdit();
                                                        }
                                                     "
                                                     class="contents"
                                                >
                                                <input
                                                    wire:model.defer="editingMessengerNetwork"
                                                    wire:keydown.enter="saveInlineMessengerValue"
                                                    wire:keydown.escape="cancelInlineMessengerEdit"
                                                    type="text"
                                                    placeholder="мережа"
                                                    class="w-24 shrink-0 border border-acc rounded-md px-2 py-1 text-[11px] text-ink focus:outline-none"
                                                    aria-label="Редагувати мережу месенджера"
                                                >
                                                <input
                                                    wire:model.defer="editingMessengerValue"
                                                    wire:keydown.enter="saveInlineMessengerValue"
                                                    wire:keydown.escape="cancelInlineMessengerEdit"
                                                    type="text"
                                                    class="flex-1 min-w-0 border border-acc rounded-md px-2 py-1 text-xs text-ink focus:outline-none"
                                                    aria-label="Редагувати месенджер"
                                                >
                                                </div>
                                            @else
                                                @if($r['value'] !== null)
                                                    <span class="min-w-0 truncate text-ink">{{ $r['value'] }}</span>
                                                @else
                                                    <span class="text-mut">—</span>
                                                @endif
                                            @endif
                                            <span class="dbm-action-grid ml-auto shrink-0 grid grid-cols-[92px_24px_24px_24px_24px] items-center justify-items-center gap-1">
                                                @if($isEditingMessenger)
                                                    <button wire:click.stop="saveInlineMessengerValue" class="text-ok-tx hover:opacity-80 px-1 py-0.5" title="Зберегти" aria-label="Зберегти">@svg('check')</button>
                                                    <button wire:click.stop="cancelInlineMessengerEdit" class="text-mut hover:text-ink px-1 py-0.5" title="Скасувати" aria-label="Скасувати">@svg('x')</button>
                                                    <button wire:click.stop="removeMessenger({{ $r['id'] }})" wire:confirm="Видалити цей месенджер?" class="text-mut hover:text-bad-tx px-1 py-0.5" title="Видалити" aria-label="Видалити">@svg('trash')</button>
                                                @else
                                                    @if($isInactiveMessenger)
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-bad-bg px-1.5 py-0.5 text-[10px] font-semibold text-bad-tx">● неактивний</span>
                                                        <button wire:click.stop="restoreMessenger({{ $r['id'] }})" class="text-ok-tx hover:opacity-80 px-1 py-0.5" title="Показати" aria-label="Показати">@svg('eye')</button>
                                                        <span class="inline-flex w-6 justify-center px-1 py-0.5 text-transparent opacity-0" aria-hidden="true">@svg('ban')</span>
                                                    @else
                                                        @if($r['is_current'] ?? false)
                                                            <span class="inline-flex items-center gap-1 rounded-md bg-ok-bg px-1.5 py-0.5 text-[10px] font-semibold text-ok-tx">● показується</span>
                                                        @endif
                                                        @if(!empty($r['pinned']))
                                                            <button wire:click.stop="unpinMessenger({{ $r['id'] }})" class="text-acc-tx hover:opacity-80 px-1 py-0.5" title="Зняти ручний режим" aria-label="Зняти ручний режим">@svg('pin')</button>
                                                        @else
                                                            <button wire:click.stop="pinMessenger({{ $r['id'] }})" class="text-mut hover:text-acc-tx px-1 py-0.5" title="Показувати цей" aria-label="Показувати цей">@svg('pin')</button>
                                                        @endif
                                                        <button wire:click.stop="deactivateMessenger({{ $r['id'] }})" wire:confirm="Деактивувати цей месенджер?" class="text-mut hover:text-bad-tx px-1 py-0.5" title="Деактивувати" aria-label="Деактивувати">@svg('ban')</button>
                                                    @endif
                                                    {{-- Chain link: dropdown для прив'язки до 1+ телефонів --}}
                                                    @php
                                                        $linkedSlots = $r['linked_slot'] ?? [];
                                                        $availableToLink = array_values(array_diff($phoneKeys, $linkedSlots));
                                                    @endphp
                                                    @if(!empty($linkedSlots) || !empty($phoneKeys))
                                                        <div class="relative" x-data="{ open: false }">
                                                            <button @click.stop="open = !open"
                                                                class="{{ !empty($linkedSlots) ? 'text-acc-tx' : 'text-mut' }} hover:text-acc-tx px-1 py-0.5"
                                                                title="{{ !empty($linkedSlots) ? implode(', ', $linkedSlots) : 'Прив\'язати до телефону' }}"
                                                                aria-label="Прив'язка до телефону">@svg('link')</button>
                                                            <div x-show="open" @click.outside="open = false" x-cloak
                                                                class="absolute right-0 top-full mt-1 z-20 bg-white border border-[#dfe3e0] rounded-lg shadow-md py-1 min-w-[160px]">
                                                                @foreach($linkedSlots as $lk)
                                                                    <div class="flex items-center justify-between px-3 py-1 gap-2">
                                                                        <span class="text-[11px] font-mono text-acc-tx truncate">{{ $lk }}</span>
                                                                        <button wire:click.stop="unlinkMessengerFromPhone({{ $r['id'] }}, '{{ $lk }}')" @click="open = false"
                                                                            class="text-mut hover:text-bad-tx text-xs leading-none shrink-0">×</button>
                                                                    </div>
                                                                @endforeach
                                                                @if(!empty($linkedSlots) && !empty($availableToLink))
                                                                    <div class="border-t border-[#edf0ed] my-1"></div>
                                                                @endif
                                                                @foreach($availableToLink as $pk)
                                                                    <button wire:click.stop="linkMessengerToPhone({{ $r['id'] }}, '{{ $pk }}')" @click="open = false"
                                                                        class="w-full text-left px-3 py-1 text-[11px] font-mono text-ink hover:bg-acc-bg hover:text-acc-tx transition-colors">
                                                                        + {{ $pk }}
                                                                    </button>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @else
                                                        <span class="inline-flex w-6 justify-center px-1 py-0.5 text-transparent opacity-0" aria-hidden="true">@svg('link')</span>
                                                    @endif
                                                    <button wire:click.stop="startInlineMessengerEdit({{ $r['id'] }})" class="text-mut hover:text-acc-tx px-1 py-0.5" title="Редагувати" aria-label="Редагувати">@svg('edit')</button>
                                                @endif
                                            </span>
                                        </div>
                                        @if($isEditingMessenger)
                                            @error('editingMessengerNetwork')
                                                <span class="ml-[6.5rem] text-[11px] text-bad-tx">{{ $message }}</span>
                                            @enderror
                                            @error('editingMessengerValue')
                                                <span class="ml-[6.5rem] text-[11px] text-bad-tx">{{ $message }}</span>
                                            @enderror
                                        @endif
                                    @elseif($type === 'price' && !empty($r['prices']))
                                        <span class="flex flex-col gap-1 min-w-0">
                                            @foreach($r['prices'] as $index => $price)
                                                <span class="flex min-h-8 items-center gap-2 min-w-0 rounded-md px-2 py-1 bg-[#f7f8f7]">
                                                    <span class="w-24 shrink-0 text-[10px] uppercase tracking-wide text-mut font-medium">
                                                        {{ $price['label'] ?: ('Ціна ' . ($index + 1)) }}
                                                    </span>
                                                    <span class="min-w-0 truncate text-ink font-semibold">{{ $price['value'] }}</span>
                                                </span>
                                            @endforeach
                                        </span>
                                    @else
                                        <span class="flex items-center gap-2 min-w-0">
                                            @if($r['value'] !== null)
                                                <span class="text-ink truncate">{{ $r['value'] }}</span>
                                            @else
                                                <span class="text-mut">—</span>
                                            @endif
                                        </span>
                                    @endif
                                    @if($type === 'messenger' && !empty($r['reserve_rows']))
                                        <div class="flex flex-col gap-1">
                                            @foreach($r['reserve_rows'] as $reserve)
                                                @php
                                                    $isEditingReserve = $editingMessengerId === $reserve['id'];
                                                    $isInactiveReserve = $reserve['state'] === 'hidden';
                                                    $messengerReserveVisible = ($reserve['is_current'] ?? false) ? 'true' : 'false';
                                                @endphp
                                                <div class="flex min-h-8 items-center gap-2 min-w-0 rounded-md px-2 py-1 {{ ($reserve['is_current'] ?? false) ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }} {{ $isInactiveReserve ? 'opacity-70' : '' }}"
                                                    x-show="!isReservesCollapsed('messenger') || {{ $messengerReserveVisible }}"
                                                    x-cloak
                                                >
                                                    <span class="w-24 shrink-0 truncate text-[10px] uppercase tracking-wide {{ ($reserve['is_current'] ?? false) ? 'text-acc-tx font-semibold' : 'text-mut' }}">{{ $reserve['label'] }} {{ $reserve['network'] }}</span>
                                                    @if($isEditingReserve)
                                                        <input
                                                            wire:model.defer="editingMessengerNetwork"
                                                            wire:keydown.enter="saveInlineMessengerValue"
                                                            wire:keydown.escape="cancelInlineMessengerEdit"
                                                            type="text"
                                                            placeholder="мережа"
                                                            class="w-24 shrink-0 border border-acc rounded-md px-2 py-1 text-[11px] text-ink focus:outline-none"
                                                            aria-label="Редагувати мережу месенджера"
                                                        >
                                                        <input
                                                            wire:model.defer="editingMessengerValue"
                                                            wire:keydown.enter="saveInlineMessengerValue"
                                                            wire:keydown.escape="cancelInlineMessengerEdit"
                                                            type="text"
                                                            class="flex-1 min-w-0 border border-acc rounded-md px-2 py-1 text-xs text-ink focus:outline-none"
                                                            aria-label="Редагувати резерв месенджера"
                                                        >
                                                    @else
                                                        <span class="min-w-0 truncate text-ink">{{ $reserve['value'] ?? '—' }}</span>
                                                    @endif
                                                    <span class="dbm-action-grid ml-auto shrink-0 grid grid-cols-[92px_24px_24px_24px_24px] items-center justify-items-center gap-1">
                                                        @if($isEditingReserve)
                                                            <button wire:click.stop="saveInlineMessengerValue" class="text-ok-tx hover:opacity-80 px-1 py-0.5" title="Зберегти" aria-label="Зберегти">@svg('check')</button>
                                                            <button wire:click.stop="cancelInlineMessengerEdit" class="text-mut hover:text-ink px-1 py-0.5" title="Скасувати" aria-label="Скасувати">@svg('x')</button>
                                                            <button wire:click.stop="removeMessenger({{ $reserve['id'] }})" wire:confirm="Видалити цей резерв месенджера?" class="text-mut hover:text-bad-tx px-1 py-0.5" title="Видалити" aria-label="Видалити">@svg('trash')</button>
                                                        @else
                                                            @if($isInactiveReserve)
                                                                <span class="inline-flex items-center gap-1 rounded-md bg-bad-bg px-1.5 py-0.5 text-[10px] font-semibold text-bad-tx">● неактивний</span>
                                                                <button wire:click.stop="restoreMessenger({{ $reserve['id'] }})" class="text-ok-tx hover:opacity-80 px-1 py-0.5" title="Показати" aria-label="Показати">@svg('eye')</button>
                                                                <span class="inline-flex w-6 justify-center px-1 py-0.5 text-transparent opacity-0" aria-hidden="true">@svg('ban')</span>
                                                            @else
                                                                @if($reserve['is_current'] ?? false)
                                                                    <span class="inline-flex items-center gap-1 rounded-md bg-ok-bg px-1.5 py-0.5 text-[10px] font-semibold text-ok-tx">● показується</span>
                                                                @endif
                                                                @if(!empty($reserve['pinned']))
                                                                    <button wire:click.stop="unpinMessenger({{ $reserve['id'] }})" class="text-acc-tx hover:opacity-80 px-1 py-0.5" title="Зняти ручний режим" aria-label="Зняти ручний режим">@svg('pin')</button>
                                                                @else
                                                                    <button wire:click.stop="pinMessenger({{ $reserve['id'] }})" class="text-mut hover:text-acc-tx px-1 py-0.5" title="Показувати цей" aria-label="Показувати цей">@svg('pin')</button>
                                                                @endif
                                                                <button wire:click.stop="deactivateMessenger({{ $reserve['id'] }})" wire:confirm="Деактивувати цей резерв месенджера?" class="text-mut hover:text-bad-tx px-1 py-0.5" title="Деактивувати" aria-label="Деактивувати">@svg('ban')</button>
                                                            @endif
                                                            <span class="inline-flex w-6 justify-center px-1 py-0.5 text-transparent opacity-0" aria-hidden="true">@svg('link')</span>
                                                            <button wire:click.stop="startInlineMessengerEdit({{ $reserve['id'] }})" class="text-mut hover:text-acc-tx px-1 py-0.5" title="Редагувати" aria-label="Редагувати">@svg('edit')</button>
                                                        @endif
                                                    </span>
                                                </div>
                                                @if($isEditingReserve)
                                                    @error('editingMessengerNetwork')
                                                        <span class="ml-[6.5rem] text-[11px] text-bad-tx" x-show="!isReservesCollapsed('messenger') || {{ $messengerReserveVisible }}" x-cloak>{{ $message }}</span>
                                                    @enderror
                                                    @error('editingMessengerValue')
                                                        <span class="ml-[6.5rem] text-[11px] text-bad-tx" x-show="!isReservesCollapsed('messenger') || {{ $messengerReserveVisible }}" x-cloak>{{ $message }}</span>
                                                    @enderror
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                    @if($type === 'messenger')
                                        <div class="mt-1.5 flex items-center gap-2 pl-2" x-show="!isReservesCollapsed('messenger')">
                                            <div
                                                x-data="{
                                                    open: false,
                                                    draft: @js((string) ($newMessengerNetwork[$r['id']] ?? '')),
                                                    wirePath: 'newMessengerNetwork.{{ $r['id'] }}',
                                                    storageKey: 'dbmanager.messenger.networks',
                                                    defaults: [
                                                        { value: 'telegram', label: 'Telegram' },
                                                        { value: 'viber', label: 'Viber' },
                                                        { value: 'whatsapp', label: 'WhatsApp' },
                                                        { value: 'messenger', label: 'Messenger' },
                                                        { value: 'signal', label: 'Signal' },
                                                        { value: 'max', label: 'MAX' },
                                                        { value: 'line', label: 'Line' },
                                                        { value: 'wechat', label: 'WeChat' },
                                                    ],
                                                    custom: [],
                                                    init() {
                                                        try {
                                                            const saved = JSON.parse(localStorage.getItem(this.storageKey) || '[]');
                                                            this.custom = Array.isArray(saved) ? saved.filter(item => item && item.value && item.label) : [];
                                                        } catch (e) {
                                                            this.custom = [];
                                                        }
                                                        this.draft = this.draft || '';
                                                    },
                                                    normalize(value) {
                                                        return (value || '').trim().toLowerCase().replace(/[^a-z0-9_ -]+/g, '').replace(/\s+/g, '_').slice(0, 32);
                                                    },
                                                    labelFor(value) {
                                                        return (value || '').trim().slice(0, 32);
                                                    },
                                                    persist() {
                                                        localStorage.setItem(this.storageKey, JSON.stringify(this.custom));
                                                    },
                                                    sync() {
                                                        this.$wire.set(this.wirePath, this.draft || '');
                                                    },
                                                    allOptions() {
                                                        const seen = new Set();
                                                        return [...this.defaults, ...this.custom].filter(option => {
                                                            const value = this.normalize(option.value);
                                                            if (!value || seen.has(value)) return false;
                                                            seen.add(value);
                                                            option.value = value;
                                                            option.label = option.label || value;
                                                            return true;
                                                        });
                                                    },
                                                    isCustom(option) {
                                                        return this.custom.some(item => this.normalize(item.value) === option.value);
                                                    },
                                                    get options() {
                                                        const query = this.normalize(this.draft);
                                                        return this.allOptions().filter(option => !query || option.value.includes(query) || option.label.toLowerCase().includes(query));
                                                    },
                                                    hasExactDraft() {
                                                        const value = this.normalize(this.draft);
                                                        return value !== '' && this.allOptions().some(option => option.value === value);
                                                    },
                                                    choose(option) {
                                                        this.draft = option.value;
                                                        this.sync();
                                                        this.open = false;
                                                    },
                                                    addCurrent() {
                                                        const value = this.normalize(this.draft);
                                                        if (!value) return;
                                                        if (!this.hasExactDraft()) {
                                                            this.custom.push({ value, label: this.labelFor(this.draft) || value });
                                                            this.persist();
                                                        }
                                                        this.draft = value;
                                                        this.sync();
                                                        this.open = false;
                                                    },
                                                    remove(option) {
                                                        this.custom = this.custom.filter(item => this.normalize(item.value) !== option.value);
                                                        if (this.normalize(this.draft) === option.value) this.draft = '';
                                                        this.sync();
                                                        this.persist();
                                                    },
                                                }"
                                                @click.outside="open = false"
                                                class="relative w-32 shrink-0"
                                            >
                                                <div class="flex min-h-8 items-center rounded-md border border-[#dfe3e0] bg-white focus-within:border-acc">
                                                    <input
                                                        x-model="draft"
                                                        @input="sync()"
                                                        @focus="open = true"
                                                        @input="open = true"
                                                        @keydown.enter.prevent="addCurrent()"
                                                        type="text"
                                                        placeholder="мережа"
                                                        class="min-w-0 flex-1 border-0 bg-transparent px-2 py-1 text-[11px] text-ink placeholder:text-mut/60 focus:outline-none focus:ring-0"
                                                        aria-label="Мережа месенджера"
                                                    >
                                                    <button type="button" @click="open = !open" class="shrink-0 px-1.5 text-mut hover:text-acc-tx" title="Вибрати мережу" aria-label="Вибрати мережу">
                                                        @svg('chevron-down')
                                                    </button>
                                                </div>
                                                <div
                                                    x-cloak
                                                    x-show="open"
                                                    class="absolute left-0 top-full z-30 mt-1 w-56 overflow-hidden rounded-lg border border-[#dfe3e0] bg-white shadow-lg"
                                                >
                                                    <div class="max-h-52 overflow-y-auto py-1">
                                                        <template x-for="option in options" :key="option.value">
                                                            <div class="flex items-center gap-1 px-1">
                                                                <button type="button" @click="choose(option)" class="min-w-0 flex-1 rounded-md px-2 py-1.5 text-left text-[11px] text-ink hover:bg-acc-bg hover:text-acc-tx">
                                                                    <span class="block truncate" x-text="option.label"></span>
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    x-show="isCustom(option)"
                                                                    @click.stop="remove(option)"
                                                                    class="shrink-0 rounded-md px-1.5 py-1 text-mut hover:bg-bad-bg hover:text-bad-tx"
                                                                    title="Видалити зі списку"
                                                                    aria-label="Видалити зі списку"
                                                                >@svg('trash')</button>
                                                            </div>
                                                        </template>
                                                        <button
                                                            type="button"
                                                            x-show="normalize(draft) && !hasExactDraft()"
                                                            @click="addCurrent()"
                                                            class="mt-1 flex w-full items-center gap-1 border-t border-[#edf0ed] px-3 py-2 text-left text-[11px] font-semibold text-acc-tx hover:bg-acc-bg"
                                                        >
                                                            <span>+ додати</span>
                                                            <span class="font-mono" x-text="normalize(draft)"></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <input
                                                wire:model.defer="newMessengerValue.{{ $r['id'] }}"
                                                type="text"
                                                placeholder="резерв: посилання, номер або код"
                                                class="flex-1 min-w-0 border border-[#dfe3e0] rounded-md px-2 py-1 text-xs text-ink focus:outline-none focus:border-acc"
                                            >
                                            <button wire:click.stop="addMessengerReserve({{ $r['id'] }})"
                                                class="shrink-0 text-mut hover:text-acc-tx px-1 py-0.5" title="Додати резерв" aria-label="Додати резерв">@svg('plus')</button>
                                        </div>
                                        @error('newMessengerValue.' . $r['id'])
                                            <span class="text-[11px] text-bad-tx pl-2 mt-0.5 block" x-show="!isReservesCollapsed('messenger')">{{ $message }}</span>
                                        @enderror
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] text-mut pl-2" x-show="!isReservesCollapsed('messenger')">
                                            <span>Якщо всі впали:</span>
                                            <button wire:click.stop="setMessengerExhaustionPolicy({{ $r['id'] }}, 'hide')"
                                                class="rounded-md px-1.5 py-0.5 border {{ ($r['exhaustion_policy'] ?? 'hide') === 'hide' ? 'bg-acc text-white border-acc' : 'border-[#dfe3e0] hover:border-acc' }}">прибрати</button>
                                            <button wire:click.stop="setMessengerExhaustionPolicy({{ $r['id'] }}, 'last')"
                                                class="rounded-md px-1.5 py-0.5 border {{ ($r['exhaustion_policy'] ?? 'hide') === 'last' ? 'bg-acc text-white border-acc' : 'border-[#dfe3e0] hover:border-acc' }}">останній</button>
                                            <button wire:click.stop="setMessengerExhaustionPolicy({{ $r['id'] }}, 'emergency')"
                                                class="rounded-md px-1.5 py-0.5 border {{ ($r['exhaustion_policy'] ?? 'hide') === 'emergency' ? 'bg-acc text-white border-acc' : 'border-[#dfe3e0] hover:border-acc' }}">аварійний</button>
                                            @if(($r['exhaustion_policy'] ?? 'hide') === 'emergency')
                                                <input
                                                    type="text"
                                                    value="{{ $r['emergency_value'] ?? '' }}"
                                                    wire:change="saveMessengerEmergencyValue({{ $r['id'] }}, $event.target.value)"
                                                    placeholder="аварійний номер / посилання"
                                                    class="flex-1 min-w-[160px] border border-[#dfe3e0] rounded-md px-2 py-0.5 text-[11px] text-ink focus:outline-none focus:border-acc"
                                                    aria-label="Аварійний номер">
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Formatting Column --}}
                        <div class="min-w-0 w-full mt-0.5">
                            @if($type === 'phone' && !empty($r['numbers']))
                                <div class="flex flex-col gap-1">
                                    @foreach($r['numbers'] as $number)
                                        @if($number['priority'] === 0)
                                             <div class="flex min-h-8 items-center">
                                                 <label class="flex w-full items-center gap-1.5 rounded-md border border-[#dfe3e0] bg-[#fafbfa] px-2 py-0.5 text-[11px] text-mut focus-within:border-acc transition-colors">
                                                     <input
                                                         type="text"
                                                         value="{{ $r['phone_format'] ?? '' }}"
                                                         wire:change="savePhoneFormat({{ $r['id'] }}, $event.target.value)"
                                                         placeholder="без формату"
                                                         class="min-w-0 flex-1 border-0 bg-transparent p-0 font-mono text-[11px] text-ink placeholder:text-mut/50 focus:outline-none focus:ring-0"
                                                         aria-label="Формат номера"
                                                     >
                                                 </label>
                                             </div>
                                             @error("phoneFormatDraft.{$r['id']}")
                                                 <span class="text-[10px] text-bad-tx block mt-0.5">{{ $message }}</span>
                                             @enderror
                                        @else
                                            @php $fmtPhoneRowVisible = $number['is_current'] ? 'true' : 'false'; @endphp
                                            <div class="flex min-h-8 items-center gap-1.5 justify-start pl-2"
                                                x-show="!isReservesCollapsed('phone') || {{ $fmtPhoneRowVisible }}"
                                                x-cloak
                                            >
                                                <button type="button" wire:click.stop="movePhoneUp({{ $number['entry_id'] }})"
                                                    class="text-mut hover:text-acc-tx p-0.5 font-bold text-xs"
                                                    title="Вгору">▲</button>
                                                <button type="button" wire:click.stop="movePhoneDown({{ $number['entry_id'] }})"
                                                    class="text-mut hover:text-acc-tx p-0.5 font-bold text-xs disabled:opacity-30"
                                                    @if($loop->last) disabled @endif
                                                    title="Вниз">▼</button>
                                                <button type="button" wire:click.stop="removeInlinePhoneNumber({{ $number['entry_id'] }})"
                                                    wire:confirm="Видалити цей номер із ланцюга?"
                                                    class="text-mut hover:text-bad-tx p-0.5 ml-2"
                                                    title="Видалити">@svg('trash')</button>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @elseif($type === 'messenger')
                                <div class="flex flex-col gap-1">
                                    {{-- Main messenger row formatting placeholder --}}
                                    <div class="flex min-h-8 items-center"
                                        @if($hasActiveMessengerReserve)
                                            x-show="!isReservesCollapsed('messenger')"
                                            x-cloak
                                        @endif
                                    >
                                        <span class="text-mut text-[11px]">—</span>
                                    </div>
                                    {{-- Reserve messenger rows --}}
                                    @if(!empty($r['reserve_rows']))
                                        @foreach($r['reserve_rows'] as $reserve)
                                            @php $fmtMsgRowVisible = ($reserve['is_current'] ?? false) ? 'true' : 'false'; @endphp
                                            <div class="flex min-h-8 items-center justify-start pl-2"
                                                x-show="!isReservesCollapsed('messenger') || {{ $fmtMsgRowVisible }}"
                                                x-cloak
                                            >
                                                <button type="button" wire:click.stop="removeMessenger({{ $reserve['id'] }})"
                                                    wire:confirm="Видалити цей резерв месенджера?"
                                                    class="text-mut hover:text-bad-tx p-0.5"
                                                    title="Видалити">@svg('trash')</button>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @else
                                <span class="text-mut text-[11px]">—</span>
                            @endif
                        </div>

                        {{-- Details --}}
                        <span class="inline-flex items-center justify-end gap-1.5 pt-1">
                            @if(isset($r['id']))
                                @if($r['state'] === 'hidden')
                                    <button wire:click="toggleSlotVisibility({{ $r['id'] }})" class="text-mut hover:text-acc-tx" title="Показати слот" aria-label="Показати слот">@svg('eye')</button>
                                @else
                                    <button wire:click="toggleSlotVisibility({{ $r['id'] }})" class="text-mut hover:text-acc-tx" title="Приховати слот" aria-label="Приховати слот">@svg('eye-off')</button>
                                @endif
                            @endif
                            @if($type === 'phone' && isset($r['id']))
                                <button wire:click="openSlot({{ $r['id'] }})" class="text-mut hover:text-acc-tx" title="Налаштування слота і формат номера" aria-label="Налаштування слота і формат номера">@svg('edit')</button>
                            @elseif($type === 'messenger' && isset($r['id']))
                                <button wire:click="openMessengerSlot({{ $r['id'] }})" class="text-mut hover:text-acc-tx" title="Налаштування месенджера" aria-label="Налаштування месенджера">@svg('edit')</button>
                            @elseif($type !== 'phone' && isset($r['id']))
                                <button wire:click="editValue({{ $r['id'] }})" class="text-mut hover:text-acc-tx" title="Редагувати значення" aria-label="Редагувати значення">@svg('edit')</button>
                            @endif
                            @if(isset($r['id']))
                                <button wire:click="removeSlotFromSite({{ $r['id'] }})"
                                    wire:confirm="Прибрати цей слот з цього сайту? Якщо він спільний — лишиться на джерелі (групі чи сайті-джерелі)."
                                    class="text-mut hover:text-bad-tx" title="Прибрати з цього сайту" aria-label="Прибрати з цього сайту">@svg('trash')</button>
                            @endif
                        </span>
                    </div>
                    @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
<livewire:slot-panel />
<livewire:messenger-panel />
<livewire:value-editor />
@if(request()->routeIs('admin.values') && $bulkReplaceOpen)
    <div class="fixed inset-0 z-40 bg-[rgba(20,26,22,0.28)]" wire:click="closeBulkReplace"></div>
    <aside class="fixed right-0 top-0 bottom-0 z-50 w-[520px] max-w-[calc(100vw-24px)] overflow-y-auto border-l border-[#dfe3e0] bg-white text-[13px] shadow-[-18px_0_40px_rgba(28,34,30,0.12)]" wire:click.stop>
        <div class="flex items-center justify-between border-b border-[#edf0ed] px-4 py-3">
            <h2 class="text-[15px] font-semibold text-acc-tx">Масова заміна</h2>
            <button type="button" wire:click="closeBulkReplace" class="text-mut hover:text-ink" aria-label="Закрити">@svg('x')</button>
        </div>
        <div class="space-y-4 px-4 py-4">
            <div class="grid grid-cols-1 gap-3">
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Знайти</label>
                    <input wire:model.live="bulkFind" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Замінити на</label>
                    <input wire:model.live="bulkReplace" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Область</label>
                    <select wire:model.live="bulkScope" class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                        <option value="current_site">Поточний сайт</option>
                        <option value="selected">Вибрані сайти</option>
                        <option value="group">Уся група</option>
                        <option value="tree">Сайт і сателіти</option>
                        <option value="all">Усі доступні сайти</option>
                    </select>
                </div>
            </div>

            <div class="rounded-lg border border-[#dfe3e0] bg-[#f6f8f6] px-3 py-2 text-[12px] text-mut">
                <div>Збігів у прев’ю: <span class="font-semibold text-ink">{{ count($bulkPreview) }}</span></div>
                @if($bulkReport)
                    <div class="mt-1">Змінено: {{ $bulkReport['values'] }} значень, {{ $bulkReport['changes'] }} замін.</div>
                @endif
            </div>

            <div class="max-h-[52vh] space-y-1 overflow-y-auto rounded-lg border border-[#dfe3e0]">
                @forelse($bulkPreview as $row)
                    <div class="flex items-center justify-between gap-2 border-b border-[#edf0ed] px-3 py-2 last:border-b-0 text-[12px]">
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-ink">{{ $row['key'] }}</div>
                            <div class="truncate text-mut">{{ $row['site'] }} · {{ $row['type'] }}</div>
                        </div>
                        <span class="shrink-0 rounded-md bg-[#eef1ee] px-2 py-0.5 text-[11px] text-mut">{{ $row['hits'] }} збіг(и)</span>
                    </div>
                @empty
                    <div class="px-3 py-4 text-center text-mut">Поки немає збігів.</div>
                @endforelse
            </div>

            <div class="flex gap-2 pt-1">
                <button type="button" wire:click="applyBulkReplace" class="inline-flex items-center gap-1.5 rounded-lg bg-acc px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                    Застосувати
                </button>
                <button type="button" wire:click="closeBulkReplace" class="rounded-lg border border-[#dfe3e0] px-3 py-2 text-xs font-semibold text-mut hover:border-acc hover:text-acc-tx">
                    Скасувати
                </button>
            </div>
        </div>
    </aside>
@endif

@if($showScopeDialog)
    <div class="fixed inset-0 z-[60] bg-[rgba(20,26,22,0.32)]" wire:click="cancelScopeDecision"></div>
    <aside class="fixed right-0 top-0 bottom-0 z-[70] w-[420px] max-w-[calc(100vw-24px)] overflow-y-auto border-l border-[#dfe3e0] bg-white text-[13px] shadow-[-18px_0_40px_rgba(28,34,30,0.12)]" wire:click.stop>
        <div class="flex items-center justify-between border-b border-[#edf0ed] px-4 py-3">
            <h2 class="text-[15px] font-semibold text-acc-tx">Область зміни</h2>
            <button type="button" wire:click="cancelScopeDecision" class="text-mut hover:text-ink" aria-label="Закрити">@svg('x')</button>
        </div>
        <div class="space-y-3 px-4 py-4">
            <p class="text-[12px] leading-5 text-mut">
                Це значення наслідують дочірні сайти. Оберіть, чи змінити тільки поточний сайт, чи також ті сайти, які зараз наслідують це значення.
            </p>
            <button type="button" wire:click="confirmScopeOnlyThisSite" class="flex w-full items-center justify-between rounded-lg border border-acc bg-acc-bg px-3 py-2 text-left text-[13px] font-semibold text-acc-tx hover:bg-[#e6edf2]">
                <span>Лише цей сайт</span>
                @svg('chevron-right')
            </button>
            <button type="button" wire:click="confirmScopeCascade" class="flex w-full items-center justify-between rounded-lg border border-[#dfe3e0] px-3 py-2 text-left text-[13px] font-semibold text-ink hover:border-acc hover:text-acc-tx">
                <span>Цей сайт + ті, що наслідують</span>
                @svg('chevron-right')
            </button>
            <button type="button" wire:click="cancelScopeDecision" class="rounded-lg border border-[#dfe3e0] px-3 py-1.5 text-xs font-semibold text-mut hover:border-acc hover:text-acc-tx">
                Скасувати
            </button>
        </div>
    </aside>
@endif

@if($canManageSites && $showEditSiteModal)
    <div class="fixed inset-0 z-20 bg-[rgba(20,26,22,0.28)]" wire:click="closeEditSite"></div>
    <aside wire:click.stop
        class="fixed right-0 top-0 bottom-0 z-30 w-[460px] max-w-[calc(100vw-24px)] overflow-y-auto border-l border-[#dfe3e0] bg-white text-[13px] shadow-[-18px_0_40px_rgba(28,34,30,0.12)]">
        <div class="flex items-center justify-between border-b border-[#edf0ed] px-4 py-3">
            <h2 class="text-[15px] font-semibold text-acc-tx">
                {{ $editingSiteId ? 'Редагувати сайт' : 'Новий сайт' }}
            </h2>
            <button type="button" wire:click="closeEditSite" class="text-mut hover:text-ink shrink-0" aria-label="Закрити">@svg('x')</button>
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
                            <option value="{{ $id }}">{{ $domain }} [ID: {{ $id }}]</option>
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
                <button type="button" wire:click="closeEditSite"
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
</div>
