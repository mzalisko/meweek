<x-slot name="breadcrumb">
    <span class="text-mut text-xs">›</span>
    <span class="text-mut text-xs">Група: {{ $siteModel?->group?->name ?? '—' }}</span>
    <span class="text-mut text-xs">›</span>
    <span class="text-xs font-semibold">{{ $siteModel?->domain ?? '—' }}</span>
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

<div class="p-3.5">
    {{-- Site context heading (also ensures domain is in the component's wire-snapshot for tests) --}}
    @if($siteModel)
        <div class="text-[11px] text-mut mb-2 hidden" aria-label="site-domain">{{ $siteModel->domain }}</div>
    @endif

    {{-- Filter chips (static placeholders — Task 5 adds interactivity) --}}
    <div class="flex gap-2 items-center text-mut text-xs mb-3">
        <span class="border border-[#dfe3e0] bg-white rounded-lg px-2.5 py-1 cursor-pointer">Тип ▾</span>
        <span class="border border-[#dfe3e0] bg-white rounded-lg px-2.5 py-1 cursor-pointer">Гео ▾</span>
        <span class="border border-[#dfe3e0] bg-white rounded-lg px-2.5 py-1 cursor-pointer">Статус ▾</span>
        <span class="border border-[#dfe3e0] bg-white rounded-lg px-2.5 py-1 cursor-pointer">@svg('search') Пошук…</span>
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
                    <div class="grid grid-cols-[24px_minmax(105px,1fr)_44px_minmax(210px,1.7fr)_50px_96px] gap-2.5 items-center px-2.5 py-2.5 mt-1 bg-white border border-[#e3e5e1] rounded-[10px] hover:border-acc-bd transition-colors">
                        {{-- Checkbox placeholder --}}
                        <span class="text-[#c0c5c1] select-none">☐</span>

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
                                <span class="inline-block bg-[#eef1ee] border border-dashed border-[#aeb6b0] rounded-md px-2 py-0.5 text-[11px] text-[#5a625d] whitespace-nowrap">✎ цього сайта</span>
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
