<x-slot name="breadcrumb">
    <span class="text-mut text-xs">›</span>
    <span class="text-mut text-xs">Усі сайти</span>
    @if($siteModel?->group)
        <span class="text-mut text-xs">›</span>
        <span class="text-mut text-xs">{{ $siteModel->group->name }}</span>
    @endif
    <span class="text-mut text-xs">›</span>
    {{-- Site context switcher --}}
    <select
        wire:model.live="site"
        class="text-xs font-semibold bg-transparent border-none outline-none cursor-pointer text-ink hover:text-acc-tx transition-colors"
        aria-label="Обрати сайт"
    >
        @foreach($groups as $group)
            <optgroup label="{{ $group->name }}">
                @foreach($group->sites->sortBy('domain') as $s)
                    <option value="{{ $s->id }}">{{ $s->domain }}</option>
                @endforeach
            </optgroup>
        @endforeach
        @if($ungroupedSites->isNotEmpty())
            <optgroup label="Без групи">
                @foreach($ungroupedSites->sortBy('domain') as $s)
                    <option value="{{ $s->id }}">{{ $s->domain }}</option>
                @endforeach
            </optgroup>
        @endif
    </select>
</x-slot>

<x-slot name="context">
    <div class="bg-acc-bg border-b border-acc-bd px-[18px] py-2 text-xs text-acc-tx flex gap-3 items-center">
        @svg('globe')
        <span>Сайт: <b>{{ $siteModel?->domain ?? '—' }}</b></span>
        @if($siteModel?->group)
            <span class="text-mut">·</span>
            <span>Група: {{ $siteModel->group->name }}</span>
        @endif
    </div>
</x-slot>

<div class="flex gap-0 min-h-0">
<div class="flex-1 min-w-0 p-3.5">
    {{-- Site context heading (also ensures domain is in the component's wire-snapshot for tests) --}}
    @if($siteModel)
        <div class="text-[11px] text-mut mb-2 hidden" aria-label="site-domain">{{ $siteModel->domain }}</div>
    @endif

    {{-- Add value button --}}
    <div class="flex justify-end mb-2">
        <button wire:click="addValue"
            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold bg-acc text-white hover:bg-acc/90 transition-colors">
            + Додати значення
        </button>
    </div>

    {{-- Filter chips (Task 5: interactive wire:model.live bindings) --}}
    <div class="flex gap-2 items-center text-mut text-xs mb-3 flex-wrap">
        {{-- Type filter --}}
        <label class="border rounded-lg px-2.5 py-1 cursor-pointer flex items-center gap-1
            {{ $type ? 'border-acc bg-acc-bg text-acc-tx font-semibold' : 'border-[#dfe3e0] bg-white' }}">
            Тип ▾
            <select wire:model.live="type" class="opacity-0 absolute w-0 h-0 pointer-events-none" aria-label="Фільтр за типом">
                <option value="">Усі типи</option>
                <option value="phone">Телефони</option>
                <option value="messenger">Месенджери</option>
                <option value="price">Ціни</option>
                <option value="text">Текст</option>
            </select>
        </label>
        @if($type)
            <button wire:click="$set('type', null)"
                class="border border-acc bg-acc-bg text-acc-tx rounded-lg px-2.5 py-1 font-semibold">
                Тип: {{ $type }} ✕
            </button>
        @endif

        {{-- Geo filter --}}
        <label class="border rounded-lg px-2.5 py-1 cursor-pointer flex items-center gap-1
            {{ $geo ? 'border-acc bg-acc-bg text-acc-tx font-semibold' : 'border-[#dfe3e0] bg-white' }}">
            Гео ▾
            <select wire:model.live="geo" class="opacity-0 absolute w-0 h-0 pointer-events-none" aria-label="Фільтр за гео">
                <option value="">Усі регіони</option>
                <option value="UA">UA</option>
                <option value="RU">RU</option>
                <option value="WORLD">WORLD</option>
            </select>
        </label>
        @if($geo)
            <button wire:click="$set('geo', null)"
                class="border border-acc bg-acc-bg text-acc-tx rounded-lg px-2.5 py-1 font-semibold">
                Гео: {{ $geo }} ✕
            </button>
        @endif

        {{-- Status filter --}}
        <label class="border rounded-lg px-2.5 py-1 cursor-pointer flex items-center gap-1
            {{ $status ? 'border-acc bg-acc-bg text-acc-tx font-semibold' : 'border-[#dfe3e0] bg-white' }}">
            Статус ▾
            <select wire:model.live="status" class="opacity-0 absolute w-0 h-0 pointer-events-none" aria-label="Фільтр за статусом">
                <option value="">Усі статуси</option>
                <option value="ok">ok</option>
                <option value="on_reserve">резерв</option>
                <option value="pinned">закріплено</option>
                <option value="exhausted">вичерпано</option>
                <option value="hidden">приховано</option>
            </select>
        </label>
        @if($status)
            <button wire:click="$set('status', null)"
                class="border border-acc bg-acc-bg text-acc-tx rounded-lg px-2.5 py-1 font-semibold">
                Статус: {{ $status }} ✕
            </button>
        @endif

        {{-- Search input — просторе поле, бордер лише у фокусі --}}
        <div class="ml-auto flex items-center gap-2 rounded-lg px-3 py-1.5 w-72 max-w-full bg-[#eef1ee] border border-transparent transition-colors focus-within:bg-white focus-within:border-acc">
            <span class="text-mut shrink-0">@svg('search')</span>
            <input
                wire:model.live.debounce.250ms="search"
                type="text"
                placeholder="Пошук за ключем…"
                class="bg-transparent outline-none placeholder-mut text-xs flex-1 min-w-0 text-ink"
                aria-label="Пошук за ключем"
            >
            @if($search)
                <button type="button" wire:click="$set('search', null)" class="text-mut hover:text-ink shrink-0 leading-none" aria-label="Очистити пошук">✕</button>
            @endif
        </div>
    </div>

    @if(empty($rows))
        <div class="text-mut text-sm py-8 text-center">Значення відсутні</div>
    @else
        @php
            $typeLabels = [
                'phone'     => ['Телефони',    'phone'],
                'messenger' => ['Месенджери',  'msg'],
                'price'     => ['Ціни',        'tag'],
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
        @endphp

        @foreach($rows as $type => $items)
            <div class="mt-3.5">
                {{-- Group header with counter --}}
                <div class="text-[11px] uppercase tracking-wide text-mut mb-1.5 flex gap-1.5 items-center">
                    @svg($typeLabels[$type][1] ?? 'tag')
                    <span>{{ $typeLabels[$type][0] ?? $type }}</span>
                    <span class="bg-[#eef1ee] border border-[#c8cec9] rounded-full px-2 py-0.5 text-[10px] leading-none">{{ count($items) }}</span>
                </div>

                @foreach($items as $r)
                    @php($st = $stateMap[$r['state']] ?? 'ok')
                    <div class="grid grid-cols-[24px_minmax(105px,1fr)_44px_minmax(210px,1.7fr)_50px_96px] gap-2.5 items-center px-2.5 py-2.5 mt-1 bg-white border border-[#e3e5e1] rounded-[10px] hover:border-acc-bd transition-colors cursor-pointer"
                        @if($type === 'phone' && isset($r['id'])) wire:click="openSlot({{ $r['id'] }})"
                        @elseif($type !== 'phone' && isset($r['id'])) wire:click="editValue({{ $r['id'] }})"
                        @endif>
                        {{-- Row checkbox --}}
                        <input type="checkbox"
                            wire:click.stop="toggleSelect({{ $r['id'] }})"
                            @checked(in_array($r['id'], $selected))
                            class="cursor-pointer accent-acc">

                        {{-- Key --}}
                        <span class="font-medium text-ink truncate" title="{{ $r['key'] }}">{{ $r['key'] }}</span>

                        {{-- Geo --}}
                        <span class="text-mut text-xs">{{ implode(',', $r['geo']) }}</span>

                        {{-- Status badge + value --}}
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-semibold text-[11px] shrink-0 bg-{{ $st }}-bg text-{{ $st }}-tx">
                                ●&nbsp;{{ $stateLabels[$r['state']] ?? $r['state'] }}
                            </span>
                            @if($r['value'] !== null)
                                <span class="text-ink truncate">{{ $r['value'] }}</span>
                            @endif
                        </span>

                        {{-- Reserves --}}
                        <span class="text-mut text-xs text-center">{{ $r['reserves'] ?: '—' }}</span>

                        {{-- Scope badge --}}
                        <span>
                            @if($r['scope'] === 'site')
                                <span class="inline-block bg-[#eef1ee] border border-dashed border-[#aeb6b0] rounded-md px-2 py-0.5 text-[11px] text-[#5a625d] whitespace-nowrap">цього сайта</span>
                            @else
                                <span class="text-mut text-xs">Група</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</div>
<livewire:slot-panel />
<livewire:value-editor />
</div>
