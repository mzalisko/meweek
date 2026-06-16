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
            <div class="mt-3 overflow-hidden border border-[#dfe3e0] bg-white rounded-lg">
                <div class="grid grid-cols-[32px_minmax(130px,1fr)_minmax(150px,1fr)_92px_minmax(280px,1.8fr)_92px_42px] gap-2 items-center px-2.5 py-2 bg-[#f6f8f6] border-b border-[#dfe3e0] text-[10px] uppercase tracking-wide text-mut">
                    <div></div>
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
                    <div class="grid grid-cols-[32px_minmax(130px,1fr)_minmax(150px,1fr)_92px_minmax(280px,1.8fr)_92px_42px] gap-2 items-start px-2.5 py-2.5 border-b border-[#edf0ed] last:border-b-0 hover:bg-[#fafbfa] transition-colors">
                        {{-- Row checkbox --}}
                        <input type="checkbox"
                            wire:click.stop="toggleSelect({{ $r['id'] }})"
                            @checked(in_array($r['id'], $selected))
                            class="cursor-pointer accent-acc">

                        {{-- Key --}}
                        <span class="font-medium text-ink truncate pt-1" title="{{ $r['key'] }}">{{ $r['key'] }}</span>

                        {{-- Geo --}}
                        <span class="min-w-0 flex flex-wrap gap-1 text-mut text-[11px] pt-1" title="{{ implode(', ', $r['geo']) }}">
                            @foreach($r['geo'] as $geo)
                                <span class="inline-flex max-w-full rounded-md bg-[#eef1ee] px-1.5 py-0.5 leading-none">{{ $geo }}</span>
                            @endforeach
                        </span>

                        {{-- Status badge --}}
                        <span class="min-w-0 pt-0.5">
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
                                        <span class="flex items-start gap-2 min-w-0 rounded-md px-2 py-1 {{ $number['is_current'] ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }} {{ $isInactive ? 'opacity-70' : '' }}">
                                            <span class="w-24 shrink-0 text-[10px] uppercase tracking-wide {{ $number['is_current'] ? 'text-acc-tx font-semibold' : 'text-mut' }}">
                                                {{ $number['priority'] === 0 ? '#1 основний' : '#1.' . $number['priority'] . ' резерв' }}
                                            </span>
                                            @if($isEditingNumber)
                                                <input
                                                    wire:model.defer="editingPhoneNumber"
                                                    wire:keydown.enter="saveInlinePhoneNumber"
                                                    wire:keydown.escape="cancelInlinePhoneEdit"
                                                    type="text"
                                                    class="w-44 max-w-full border border-acc rounded-md px-2 py-1 text-xs text-ink focus:outline-none"
                                                    aria-label="Редагувати номер"
                                                >
                                                <span class="ml-auto shrink-0 flex items-center gap-1">
                                                    <button wire:click.stop="saveInlinePhoneNumber" class="text-ok-tx hover:opacity-80 px-1 py-0.5" title="Зберегти" aria-label="Зберегти">@svg('check')</button>
                                                    <button wire:click.stop="cancelInlinePhoneEdit" class="text-mut hover:text-ink px-1 py-0.5" title="Скасувати" aria-label="Скасувати">@svg('x')</button>
                                                    <button wire:click.stop="removeInlinePhoneNumber({{ $number['entry_id'] }})" wire:confirm="Видалити цей номер із ланцюга?" class="text-mut hover:text-bad-tx px-1 py-0.5" title="Видалити" aria-label="Видалити">@svg('trash')</button>
                                                </span>
                                            @else
                                                <span class="min-w-0 truncate text-ink pt-0.5">{{ $number['e164'] }}</span>
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
                                                    <button wire:click.stop="startInlinePhoneEdit({{ $number['entry_id'] }})" class="text-mut hover:text-acc-tx px-1 py-0.5" title="Редагувати номер" aria-label="Редагувати номер">@svg('edit')</button>
                                                </span>
                                            @endif
                                        </span>
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
                                        <div class="mt-1.5 flex flex-col gap-1">
                                            <div class="text-[10px] uppercase tracking-wide text-mut">Месенджери під номером</div>
                                            @foreach($r['messengers'] as $m)
                                                <div class="flex flex-wrap items-center gap-1.5 rounded-md border border-[#dfe3e0] bg-[#fcfcfb] px-2 py-1 text-[10px] text-mut">
                                                    <span class="font-semibold text-ink">{{ $m['name'] }}</span>
                                                    <span>·</span>
                                                    <span>{{ ucfirst($m['network']) }}</span>
                                                    @if(!empty($m['linked_slot']))
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-[#eef1ee] px-1.5 py-0.5" title="Ключ месенджера: {{ $m['key'] }}">
                                                            {{ ucfirst($m['network'] ?? 'msg') }}
                                                        </span>
                                                    @endif
                                                    @if(!empty($m['url']))
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-[#eef1ee] px-1.5 py-0.5">
                                                            URL <span class="font-mono text-ink truncate max-w-[180px]">{{ $m['url'] }}</span>
                                                        </span>
                                                    @endif
                                                </div>
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
                                        <span class="flex items-start gap-2 min-w-0 rounded-md px-2 py-1 {{ ($r['is_current'] ?? false) ? 'bg-acc-bg' : 'bg-[#f7f8f7]' }}">
                                            <span class="w-12 shrink-0 text-[10px] uppercase tracking-wide {{ ($r['is_current'] ?? false) ? 'text-acc-tx font-semibold' : 'text-mut' }}">#1</span>
                                            <span class="w-20 shrink-0 text-[11px] {{ ($r['is_current'] ?? false) ? 'text-acc-tx font-semibold' : 'text-mut' }}">{{ ucfirst($r['network'] ?? 'msg') }}</span>
                                            @if($r['value'] !== null)
                                                <span class="text-ink truncate">{{ $r['value'] }}</span>
                                            @else
                                                <span class="text-mut">—</span>
                                            @endif
                                            @if(!empty($r['pinned']))
                                                <span class="ml-auto shrink-0 text-acc-tx" title="закріплено">@svg('pin')</span>
                                            @endif
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
                                        <div class="mt-1.5 flex flex-col gap-1">
                                            @foreach($r['reserve_rows'] as $reserve)
                                                <div class="flex flex-wrap items-center gap-1.5 rounded-md border border-[#dfe3e0] bg-[#fcfcfb] px-2 py-1 text-[10px] text-mut">
                                                    <span class="inline-flex items-center rounded-md bg-[#eef1ee] px-1.5 py-0.5 font-semibold text-mut">{{ $reserve['label'] }}</span>
                                                    <span class="font-semibold text-ink">{{ ucfirst($reserve['network']) }}</span>
                                                    <span class="text-mut">·</span>
                                                    <span class="truncate max-w-[240px]">{{ $reserve['value'] ?? '—' }}</span>
                                                    @if(!empty($reserve['url']))
                                                        <span class="inline-flex items-center rounded-md bg-[#eef1ee] px-1.5 py-0.5" title="{{ $reserve['url'] }}">
                                                            посилання
                                                        </span>
                                                    @endif
                                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 bg-{{ $reserve['state'] === 'on_reserve' ? 'warn' : 'bad' }}-bg text-{{ $reserve['state'] === 'on_reserve' ? 'warn' : 'bad' }}-tx">
                                                        ● {{ $reserve['state'] === 'on_reserve' ? 'резерв' : 'приховано' }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </span>
                            @endif
                        </span>

                        {{-- Scope badge --}}
                        <span class="min-w-0 pt-1">
                            @if($r['scope'] === 'site')
                                <span class="inline-block bg-[#eef1ee] border border-dashed border-[#aeb6b0] rounded-md px-2 py-0.5 text-[11px] text-[#5a625d] whitespace-nowrap">цього сайта</span>
                            @else
                                <span class="text-mut text-xs">Група</span>
                            @endif
                        </span>

                        {{-- Details --}}
                        <span class="text-right pt-1">
                            @if($type === 'phone' && isset($r['id']))
                                <button wire:click="openSlot({{ $r['id'] }})" class="text-mut hover:text-acc-tx" title="Налаштування слота" aria-label="Налаштування слота">@svg('edit')</button>
                            @elseif($type !== 'phone' && isset($r['id']))
                                <button wire:click="editValue({{ $r['id'] }})" class="text-mut hover:text-acc-tx" title="Редагувати значення" aria-label="Редагувати значення">@svg('edit')</button>
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
