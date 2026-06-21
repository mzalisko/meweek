<x-slot name="breadcrumb">
    <div class="flex items-center gap-3 ml-2">
        <span class="text-mut text-sm select-none">/</span>
        <div class="inline-flex items-center bg-[#f4f5f3] px-3 py-1.5 rounded-lg border border-[#e3e5e1] text-xs font-bold text-ink select-none">
            Стан сайтів
        </div>
    </div>
</x-slot>

<x-slot name="context">
    <div class="bg-acc-bg border-b border-acc-bd px-[18px] py-2 text-xs text-acc-tx flex gap-3 items-center">
        @svg('alert')
        <span>Моніторинг ліній зв'язку: розподіл сайтів за робочим статусом.</span>
    </div>
</x-slot>

<div class="relative h-full w-full p-4">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[18px] font-semibold text-acc-tx">Стан сайтів</h1>
            <p class="mt-0.5 text-[12px] text-mut">Моніторинг активних ліній зв'язку та резервів по всіх підключених ресурсах.</p>
        </div>
    </div>

    {{-- Таби --}}
    <div class="mb-5 flex border-b border-[#edf0ed]">
        <button type="button" wire:click="selectTab('reserves')" 
            class="px-4 py-2 text-xs font-bold transition-all relative -mb-px {{ $activeTab === 'reserves' ? 'border-b-2 border-acc text-acc-tx' : 'text-mut hover:text-ink' }}">
            На резервах ({{ $reservesCount }})
        </button>
        <button type="button" wire:click="selectTab('primary')" 
            class="px-4 py-2 text-xs font-bold transition-all relative -mb-px {{ $activeTab === 'primary' ? 'border-b-2 border-acc text-acc-tx' : 'text-mut hover:text-ink' }}">
            На основних ({{ $primaryCount }})
        </button>
    </div>

    {{-- Сітка квадратних карток --}}
    @php
        $displaySites = $activeTab === 'reserves' ? $onReserveSites : $onPrimarySites;
    @endphp

    @if(empty($displaySites))
        <div class="rounded-lg border border-dashed border-[#dfe3e0] bg-white px-3 py-12 text-center text-xs text-mut">
            Немає сайтів у цій категорії.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @foreach($displaySites as $siteData)
                @php
                    $site = $siteData['model'];
                    $slots = $siteData['slots'];
                    
                    // Обчислення статусу онлайн
                    $tokens = $site->tokens;
                    $lastSeen = $tokens->whereNull('revoked_at')->max('last_seen_at');
                    $hasActiveToken = $tokens->whereNull('revoked_at')->isNotEmpty();
                    $isOnline = false;
                    if ($hasActiveToken && $lastSeen) {
                        $isOnline = \Illuminate\Support\Carbon::parse($lastSeen)->gt(now()->subMinutes(15));
                    }
                @endphp
                <div class="bg-white border {{ $siteData['hasReserve'] ? 'border-yellow-300 shadow-yellow-50/50' : 'border-[#dfe3e0]' }} hover:border-acc hover:shadow-md transition-all rounded-lg p-3 flex flex-col justify-between shadow-sm relative group h-[170px]">
                    
                    {{-- Шапка картки --}}
                    <div>
                        <div class="flex items-start justify-between gap-1">
                            <span class="font-bold font-mono text-ink text-xs truncate max-w-[120px]" title="{{ $site->domain }}">
                                {{ $site->domain }}
                            </span>
                            
                            {{-- Онлайн-індикатор --}}
                            @if($isOnline)
                                <span class="h-2 w-2 rounded-full bg-ok-tx shrink-0 mt-1" title="В мережі"></span>
                            @else
                                <span class="h-2 w-2 rounded-full bg-bad-tx animate-pulse shrink-0 mt-1" title="Поза мережею"></span>
                            @endif
                        </div>
                        <div class="text-[10px] text-mut truncate">
                            {{ $site->group?->name ?? 'Без групи' }}
                        </div>
                    </div>

                    {{-- Вміст: список слотів та їхні статуси --}}
                    <div class="my-1.5 flex-1 flex flex-col justify-start overflow-y-auto max-h-[85px] space-y-1 custom-scrollbar pr-0.5">
                        @forelse($slots as $slot)
                            <div class="flex items-center justify-between text-[9px] bg-[#fafbfa] border border-[#f1f3f1] px-1.5 py-0.5 rounded">
                                <span class="text-ink truncate max-w-[90px]" title="{{ $slot['key'] }}">
                                    #{{ $slot['key'] }}
                                </span>
                                @if($slot['state'] === 'on_reserve')
                                    <span class="text-yellow-600 font-bold tracking-tight uppercase">Reserve</span>
                                @elseif($slot['state'] === 'exhausted')
                                    <span class="text-red-600 font-bold tracking-tight uppercase text-[8px]">Exhaust</span>
                                @else
                                    <span class="text-ok-tx font-medium uppercase text-[8px]">Primary</span>
                                @endif
                            </div>
                        @empty
                            <div class="text-[10px] text-mut/50 text-center italic mt-2">Немає слотів</div>
                        @endforelse
                    </div>

                    {{-- Підвал картки --}}
                    <div class="border-t border-[#edf0ed] pt-1.5 flex items-center justify-between mt-auto shrink-0">
                        <a href="{{ route('admin.site', ['site' => $site->id]) }}" wire:navigate
                            class="text-[10px] font-semibold text-acc-tx hover:underline inline-flex items-center gap-1">
                            @svg('grid', 12) Керувати
                        </a>
                        <span class="text-[9px] text-mut font-mono">ID: {{ $site->id }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
