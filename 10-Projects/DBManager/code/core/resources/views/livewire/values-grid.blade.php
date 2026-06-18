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
                <span class="text-ink font-mono font-medium">{{ $siteModel?->domain ?? 'Оберіть сайт' }}</span>
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
                            <span>{{ $s->domain }}</span>
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
        <span>Сайт: <b>{{ $siteModel?->domain ?? '—' }}</b></span>
        @if($siteModel?->group)
            <span class="text-mut">·</span>
            <span>Група: {{ $siteModel->group->name }}</span>
        @endif
    </div>
</x-slot>

<div class="flex gap-0 min-h-0" data-values-grid-root>
<div class="flex-1 min-w-0 p-3.5">
    {{-- Site context heading (also ensures domain is in the component's wire-snapshot for tests) --}}
    @if($siteModel)
        <div class="text-[11px] text-mut mb-2 hidden" aria-label="site-domain">{{ $siteModel->domain }}</div>
    @endif

    {{-- Add value button --}}
    @if($canEditCurrentSite)
        <div class="flex justify-end mb-2">
            <button wire:click="addValue"
                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold bg-acc text-white hover:bg-acc/90 transition-colors">
                + Додати значення
            </button>
        </div>
    @endif

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

        {{-- Search input --}}
        <div class="ml-auto flex items-center gap-2 rounded-lg px-3 py-1.5 w-72 max-w-full bg-[#eef1ee] transition-colors focus-within:bg-white">
            <span class="text-mut shrink-0">@svg('search')</span>
            <input
                wire:model.live.debounce.250ms="search"
                type="text"
                placeholder="Пошук за ключем або значенням…"
                class="bg-transparent outline-none placeholder-mut text-xs flex-1 min-w-0 text-ink border-0 shadow-none focus:ring-0 focus:outline-none"
                aria-label="Пошук за ключем або значенням"
            >
            @if($search)
                <button type="button" wire:click="$set('search', null)" class="text-mut hover:text-ink shrink-0 leading-none" aria-label="Очистити пошук">✕</button>
            @endif
        </div>
    </div>

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
            <div class="mt-3 border border-[#dfe3e0] bg-white rounded-lg">
                <div class="grid grid-cols-[minmax(130px,1fr)_minmax(150px,1fr)_92px_minmax(280px,1.8fr)_92px_68px] gap-2 items-center px-2.5 py-2 bg-[#f6f8f6] border-b border-[#dfe3e0] rounded-t-lg text-[10px] uppercase tracking-wide text-mut">
                    <div class="flex gap-1.5 items-center">
                        @svg($typeLabels[$type][1] ?? 'tag')
                        <span>{{ $typeLabels[$type][0] ?? $type }}</span>
                        <span class="bg-[#eef1ee] border border-[#c8cec9] rounded-full px-2 py-0.5 text-[10px] leading-none">{{ count($items) }}</span>
                    </div>
                    <div>Гео</div>
                    <div>Стан</div>
                    <div>Значення / резерви</div>
                    <div>Область</div>
                    <div></div>
                </div>

                @foreach($items as $r)
                    @php
                        $st = $stateMap[$r['state']] ?? 'ok';
                    @endphp
                    <div class="grid grid-cols-[minmax(130px,1fr)_minmax(150px,1fr)_92px_minmax(280px,1.8fr)_92px_68px] gap-2 items-start px-2.5 py-2.5 border-b border-[#edf0ed] last:border-b-0 last:rounded-b-lg hover:bg-[#fafbfa] transition-colors">
                        {{-- Key --}}
                        <span class="font-mono text-[11px] text-[#3c5a42] bg-[#eef5ee] border border-[#c4d6c6] rounded-md px-1.5 py-0.5 truncate inline-block w-fit mt-0.5" title="{{ $r['key'] }}">{{ $r['key'] }}</span>

                        {{-- Geo --}}
                        <span class="min-w-0 flex flex-wrap gap-1 text-mut text-[11px] pt-1" title="{{ implode(', ', $r['geo']) }}">
                            @foreach($r['geo'] as $geo)
                                <span class="inline-flex max-w-full rounded-md bg-[#eef1ee] px-1.5 py-0.5 leading-none">{{ $geo }}</span>
                            @endforeach
                        </span>

                        {{-- Status badge --}}
                        <span class="min-w-0">
                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 font-semibold text-[11px] bg-{{ $st }}-bg text-{{ $st }}-tx">
                                ● {{ $stateLabels[$r['state']] ?? $r['state'] }}
                            </span>
                        </span>

                        {{-- Value / inline phone edit --}}
                        <span class="min-w-0">
                            @if($type === 'phone' && !empty($r['numbers']))
                                <span class="flex flex-col gap-1">
                                    @foreach($r['numbers'] as $number)
                                        @php
                                            $isEditingNumber = $editingPhoneEntryId === $number['entry_id'];
                                            $numberStatus = $number['status'] ?? 'active';
                                            $isInactive = $numberStatus !== 'active';
                                            $isPinned = $number['is_pinned'] ?? false;
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
                                                class="flex items-start gap-2 min-w-0 rounded-md px-2 py-1 {{ $number['is_current'] ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }} {{ $isInactive ? 'opacity-70' : '' }}"
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
                                                <span class="shrink-0 flex items-center gap-1">
                                                    <button wire:click.stop="saveInlinePhoneNumber" class="text-ok-tx hover:opacity-80 px-1 py-0.5" title="Зберегти" aria-label="Зберегти">@svg('check')</button>
                                                    <button wire:click.stop="cancelInlinePhoneEdit" class="text-mut hover:text-ink px-1 py-0.5" title="Скасувати" aria-label="Скасувати">@svg('x')</button>
                                                    <button wire:click.stop="removeInlinePhoneNumber({{ $number['entry_id'] }})" wire:confirm="Видалити цей номер із ланцюга?" class="text-mut hover:text-bad-tx px-1 py-0.5" title="Видалити" aria-label="Видалити">@svg('trash')</button>
                                                </span>
                                            </span>
                                        @else
                                            <span class="flex items-start gap-2 min-w-0 rounded-md px-2 py-1 {{ $number['is_current'] ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }} {{ $isInactive ? 'opacity-70' : '' }}">
                                                <span class="w-24 shrink-0 text-[10px] uppercase tracking-wide {{ $number['is_current'] ? 'text-acc-tx font-semibold' : 'text-mut' }}">
                                                    {{ $number['priority'] === 0 ? '#1 основний' : '#1.' . $number['priority'] . ' резерв' }}
                                                </span>
                                                <span class="min-w-0 truncate text-ink">{{ $number['e164'] }}</span>
                                                <span class="ml-auto shrink-0 flex flex-wrap justify-end gap-1">
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
                                                <span class="ml-[5.5rem] text-[11px] text-bad-tx">{{ $message }}</span>
                                            @enderror
                                        @endif
                                    @endforeach
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
                                </span>
                            @else
                                <span class="flex flex-col gap-1 min-w-0">
                                    @if($r['state'] === 'exhausted' && in_array($r['exhaustion_policy'] ?? null, ['last', 'emergency'], true))
                                        <span class="shrink-0 rounded-md bg-bad-bg px-1.5 py-0.5 text-[10px] font-semibold text-bad-tx">
                                            {{ $r['exhaustion_policy'] === 'emergency' ? 'аварійний' : 'останній' }}
                                        </span>
                                    @endif
                                    @if($type === 'messenger')
                                        @php
                                            $isEditingMessenger = $editingMessengerId === $r['id'];
                                            $isInactiveMessenger = !($r['enabled'] ?? true);
                                        @endphp
                                        <span class="flex items-start gap-2 min-w-0 rounded-md px-2 py-1 {{ ($r['is_current'] ?? false) ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }} {{ $isInactiveMessenger ? 'opacity-70' : '' }}">
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
                                            <span class="ml-auto shrink-0 flex flex-wrap justify-end gap-1">
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
                                        </span>
                                        @if($isEditingMessenger)
                                            @error('editingMessengerValue')
                                                <span class="ml-[6.5rem] text-[11px] text-bad-tx">{{ $message }}</span>
                                            @enderror
                                        @endif
                                    @elseif($type === 'price' && !empty($r['prices']))
                                        <span class="flex flex-col gap-1 min-w-0">
                                            @foreach($r['prices'] as $index => $price)
                                                <span class="flex items-start gap-2 min-w-0 rounded-md px-2 py-1 bg-[#f7f8f7]">
                                                    <span class="w-24 shrink-0 text-[10px] uppercase tracking-wide text-mut font-medium">
                                                        {{ $price['label'] ?: ('Ціна ' . ($index + 1)) }}
                                                    </span>
                                                    <span class="min-w-0 truncate text-ink font-semibold">{{ $price['value'] }}</span>
                                                    <span class="ml-auto shrink-0 flex flex-wrap justify-end gap-1">
                                                        @foreach($price['geo'] ?? [] as $g)
                                                            <span class="inline-flex rounded-md bg-[#eef1ee] px-1.5 py-0.5 text-[10px] text-mut font-semibold leading-none">{{ $g }}</span>
                                                        @endforeach
                                                    </span>
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
                                                @endphp
                                                <div class="flex items-start gap-2 min-w-0 rounded-md px-2 py-1 {{ ($reserve['is_current'] ?? false) ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }} {{ $isInactiveReserve ? 'opacity-70' : '' }}">
                                                    <span class="w-24 shrink-0 truncate text-[10px] uppercase tracking-wide {{ ($reserve['is_current'] ?? false) ? 'text-acc-tx font-semibold' : 'text-mut' }}">{{ $reserve['label'] }} {{ $reserve['network'] }}</span>
                                                    @if($isEditingReserve)
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
                                                    <span class="ml-auto shrink-0 flex flex-wrap justify-end gap-1">
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
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(false && $type === 'messenger')
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <select wire:model="newMessengerNetwork.{{ $r['id'] }}"
                                                class="w-24 shrink-0 border border-[#dfe3e0] rounded-md px-2 py-1 text-[11px] focus:outline-none focus:border-acc">
                                                <option value="">мережа</option>
                                                <option value="telegram">Telegram</option>
                                                <option value="viber">Viber</option>
                                                <option value="whatsapp">WhatsApp</option>
                                                <option value="messenger">Messenger</option>
                                            </select>
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
                                            <span class="text-[11px] text-bad-tx">{{ $message }}</span>
                                        @enderror
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] text-mut">
                                            <span>Якщо всі впали:</span>
                                            <button wire:click.stop="setMessengerExhaustionPolicy({{ $r['id'] }}, 'hide')"
                                                class="rounded-md px-1.5 py-0.5 border {{ ($r['exhaustion_policy'] ?? 'hide') === 'hide' ? 'bg-acc text-white border-acc' : 'border-[#dfe3e0] hover:border-acc' }}">прибрати</button>
                                            <button wire:click.stop="setMessengerExhaustionPolicy({{ $r['id'] }}, 'last')"
                                                class="rounded-md px-1.5 py-0.5 border {{ ($r['exhaustion_policy'] ?? 'hide') === 'last' ? 'bg-acc text-white border-acc' : 'border-[#dfe3e0] hover:border-acc' }}">останній</button>
                                        </div>
                                    @endif
                                </span>
                            @endif
                        </span>

                        {{-- Scope badge --}}
                        <span class="min-w-0 pt-1">
                            @php($source = $r['source'] ?? ($r['scope'] === 'site' ? 'current_site' : 'group'))
                            @if($source === 'current_site')
                                <span class="inline-block bg-[#eef1ee] border border-dashed border-[#aeb6b0] rounded-md px-2 py-0.5 text-[11px] text-[#5a625d] whitespace-nowrap">цього сайту</span>
                            @elseif($source === 'parent_site')
                                <span class="inline-block bg-acc-bg border border-acc-bd rounded-md px-2 py-0.5 text-[11px] text-acc-tx whitespace-nowrap">сайт-джерело</span>
                            @else
                                <span class="inline-block rounded-md bg-[#f4f5f3] px-2 py-0.5 text-[11px] text-mut whitespace-nowrap">група</span>
                            @endif
                        </span>

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
                                <button wire:click="openSlot({{ $r['id'] }})" class="text-mut hover:text-acc-tx" title="Налаштування слота" aria-label="Налаштування слота">@svg('edit')</button>
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
        @endforeach
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
</div>
