<div>
<aside class="w-[420px] shrink-0 bg-white border-l border-[#e3e5e1] overflow-y-auto text-[13px]">
@if($open && $value && $slot)
<div class="p-4">

    {{-- Header --}}
    <div class="flex justify-between items-center">
        <b class="text-acc-tx flex items-center gap-1.5">@svg('phone') Слот: {{ $value->key }}</b>
        <button wire:click="$set('open', false)" class="text-mut hover:text-ink" aria-label="Закрити">@svg('x')</button>
    </div>
    <div class="text-[11px] text-mut mt-1 flex items-center gap-1">
        @if($value->scope_type === 'site')<span class="text-acc-tx">@svg('edit')</span> Перекриття цього сайта @else Значення групи @endif
    </div>

    {{-- Гео-мітки --}}
    <div class="mt-4">
        <div class="text-[11px] uppercase tracking-[.06em] text-mut mb-1.5">Гео-мітки</div>
        <div class="flex flex-wrap gap-1.5">
            @foreach($allGeoTags as $gt)
                <label class="cursor-pointer inline-flex items-center rounded-[9px] border px-2.5 py-0.5 text-[11px] transition-colors
                    {{ in_array($gt->id, $geoTagIds) ? 'border-acc bg-acc-bg text-acc-tx font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">
                    <input type="checkbox" wire:model.live="geoTagIds" value="{{ $gt->id }}" class="sr-only">
                    {{ $gt->code }}
                </label>
            @endforeach
        </div>
        @if(count($geoTagIds) === 0)
            <p class="mt-1 text-[11px] text-bad-tx">Вибери хоча б одну гео-мітку (напр. WORLD).</p>
        @endif
    </div>

    {{-- Ланцюг номерів --}}
    <div class="mt-4">
        <div class="text-[11px] uppercase tracking-[.06em] text-mut mb-1.5">Ланцюг номерів</div>

        {{-- Поточний стан слота --}}
        @php
            $activeCount   = $entries->filter(fn($e) => ($e->phoneNumber->status ?? '') === 'active')->count();
            $totalCount    = $entries->count();
            $resolvedState = $resolved?->state ?? 'exhausted';
        @endphp

        @if($totalCount === 0)
            <div class="mb-3 flex items-center gap-2 rounded-[10px] border border-[#dfe3e0] bg-[#fafbfa] px-3 py-2 text-[12px] text-mut">
                @svg('alert') Додайте хоча б один номер щоб слот почав працювати.
            </div>
        @elseif($resolvedState === 'exhausted')
            <div class="mb-3 rounded-[10px] border border-bad-tx/30 bg-bad-bg px-3 py-2 text-[12px]">
                <div class="flex items-center gap-2 text-bad-tx font-medium">@svg('alert') Слот не показується — всі номери впали</div>
                <div class="mt-1 text-mut">
                    @if($totalCount === 1) Єдиний номер деактивований. Поверніть його або додайте резервний.
                    @else Всі {{ $totalCount }} номери позначені «down».
                    @endif
                </div>
            </div>
        @elseif($resolvedState === 'pinned')
            <div class="mb-3 flex items-center gap-2 rounded-[10px] border border-acc-bd bg-acc-bg px-3 py-2 text-[12px] text-acc-tx font-medium">
                @svg('pin') Ручний режим: {{ $resolved->number }}
            </div>
        @elseif($resolvedState === 'on_reserve')
            <div class="mb-3 rounded-[10px] border border-warn-tx/25 bg-warn-bg px-3 py-2 text-[12px]">
                <div class="flex items-center gap-2 text-warn-tx font-medium">@svg('alert') Показується резерв (основний впав)</div>
                <div class="mt-0.5 font-mono text-ink">{{ $resolved->number }}</div>
            </div>
        @else
            {{-- ok: нічого не показуємо — все штатно --}}
        @endif

        @foreach($entries as $i => $entry)
            @php
                $isCurrent = $resolved && $resolved->entryId === $entry->id;
                $status    = $entry->phoneNumber->status ?? 'unknown';
                $e164      = $entry->phoneNumber->e164 ?? '—';
                $isPinned  = $slot->pinned_number_entry_id === $entry->id;
                $editing   = $editingEntryId === $entry->id;
            @endphp
            <div class="border rounded-[10px] px-3 py-2 mt-1.5
                @if($isCurrent) border-[1.5px] border-[#5f7d6e] bg-[#eef4f0]
                @elseif($status !== 'active') border-[#e3e5e1] bg-[#fafbfa] opacity-[0.55]
                @else border-[#e3e5e1] bg-[#fafbfa] @endif">

                {{-- Рядок 1: номер + статус --}}
                <div class="flex items-center gap-2">
                    <span class="text-mut shrink-0">@svg('grip')</span>
                    <b class="shrink-0">#{{ $i }} {{ $i === 0 ? 'основний' : 'резерв' }}</b>
                    @if($editing)
                        <input type="text" wire:model="editE164"
                            class="flex-1 min-w-0 border border-[#dfe3e0] rounded px-2 py-0.5 text-[12px] focus:outline-none focus:border-acc">
                        <button wire:click="saveNumber" class="shrink-0 text-ok-tx hover:opacity-80 px-0.5" title="Зберегти" aria-label="Зберегти номер">@svg('check')</button>
                        <button wire:click="cancelEdit" class="shrink-0 text-mut hover:text-ink px-0.5" title="Скасувати" aria-label="Скасувати">@svg('x')</button>
                    @else
                        <span class="text-ink truncate">{{ $e164 }}</span>
                        @if($isPinned)<span class="text-acc-tx shrink-0" title="Закріплено">@svg('pin')</span>@endif
                        @if($isCurrent)
                            <span class="ml-auto shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold bg-ok-bg text-ok-tx whitespace-nowrap">● показується</span>
                        @elseif($status !== 'active')
                            <span class="ml-auto shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold bg-bad-bg text-bad-tx">down</span>
                        @endif
                    @endif
                </div>

                {{-- Рядок 2: дії --}}
                @unless($editing)
                <div class="flex items-center gap-0.5 mt-1.5 text-[#9aa39c]">
                    <div class="flex items-center gap-1.5 mr-auto">
                        @if($status === 'down')
                            <button wire:click="setNumberStatus({{ $entry->id }}, 'active')" class="border border-ok-tx/50 text-ok-tx rounded-md px-2 py-0.5 text-[11px] hover:bg-ok-bg whitespace-nowrap">Повернути</button>
                        @else
                            @if(! $isCurrent)
                                <button wire:click="pin({{ $entry->id }})" class="border border-acc text-acc-tx rounded-md px-2 py-0.5 text-[11px] hover:bg-acc-bg whitespace-nowrap">Показувати цей</button>
                            @endif
                            <button wire:click="setNumberStatus({{ $entry->id }}, 'down')" wire:confirm="Позначити номер неактивним?" class="text-[11px] text-mut hover:text-bad-tx whitespace-nowrap">деактивувати</button>
                        @endif
                    </div>
                    <button wire:click="startEditNumber({{ $entry->id }})" class="hover:text-acc-tx px-1 py-0.5" title="Редагувати номер" aria-label="Редагувати номер">@svg('edit')</button>
                    @if($i > 0)
                    <button wire:click="moveUp({{ $entry->id }})" class="hover:text-ink px-1 py-0.5" title="Вгору" aria-label="Вгору">@svg('chevron-up')</button>
                    @else
                    <span class="px-1 py-0.5 w-5"></span>{{-- placeholder to keep layout --}}
                    @endif
                    <button wire:click="moveDown({{ $entry->id }})" class="hover:text-ink px-1 py-0.5" title="Вниз" aria-label="Вниз">@svg('chevron-down')</button>
                    <button wire:click="removeNumber({{ $entry->id }})" wire:confirm="Видалити цей номер із ланцюга?" class="hover:text-bad-tx px-1 py-0.5" title="Видалити" aria-label="Видалити">@svg('trash')</button>
                </div>
                @endunless
            </div>
        @endforeach
        @error('editE164')<p class="mt-1 text-[11px] text-bad-tx">{{ $message }}</p>@enderror

        {{-- Додати резерв --}}
        <div class="mt-2 flex items-center gap-2">
            <input type="text"
                wire:model="newNumber"
                placeholder="+380XXXXXXXXX"
                class="flex-1 min-w-0 border border-[#dfe3e0] rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:border-acc">
            <button wire:click="addNumber"
                class="shrink-0 inline-flex items-center gap-1 bg-acc text-white rounded-lg px-3 py-1.5 text-[12px] font-semibold hover:opacity-90 whitespace-nowrap">@svg('plus') додати резерв</button>
        </div>
        @error('newNumber')
            <p class="mt-1 text-[11px] text-bad-tx">{{ $message }}</p>
        @enderror
    </div>

    {{-- Прив'язані месенджери --}}
    @if($messengers->isNotEmpty())
    <div class="mt-4">
        <div class="text-[11px] uppercase tracking-[.06em] text-mut mb-1.5">Прив'язані месенджери</div>
        @foreach($messengers as $m)
            @php $enabled = $m->content['enabled'] ?? true; $network = $m->content['network'] ?? '—'; @endphp
            <div class="flex items-center gap-2 border border-[#e3e5e1] rounded-[10px] px-3 py-2 mt-1.5 bg-[#fafbfa] {{ $enabled ? '' : 'opacity-[0.55]' }}">
                <span class="flex-1 flex items-center gap-1.5 truncate"><span class="text-mut shrink-0">@svg('msg')</span> {{ ucfirst($network) }}</span>
                <button wire:click="toggleMessenger({{ $m->id }})"
                    class="shrink-0 text-[11px] font-semibold rounded-md px-2 py-0.5 {{ $enabled ? 'bg-ok-bg text-ok-tx' : 'bg-[#eef1ee] text-mut' }}">
                    {{ $enabled ? 'увімкнено' : 'вимкнено' }}
                </button>
                <button wire:click="unlinkMessenger({{ $m->id }})" wire:confirm="Від'язати цей месенджер від слота?"
                    class="shrink-0 text-mut hover:text-bad-tx px-0.5" title="Від'язати">@svg('x')</button>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Доступні для прив'язки месенджери --}}
    @if($availableMessengers->isNotEmpty())
    <div class="mt-3">
        <div class="text-[11px] uppercase tracking-[.06em] text-mut mb-1.5">Прив'язати месенджер</div>
        @foreach($availableMessengers as $m)
            @php $network = $m->content['network'] ?? '—'; @endphp
            <div class="flex items-center gap-2 border border-[#e3e5e1] rounded-[10px] px-3 py-2 mt-1.5 bg-[#fafbfa]">
                <span class="flex-1 flex items-center gap-1.5 truncate text-mut"><span class="shrink-0">@svg('msg')</span> {{ ucfirst($network) }}</span>
                <button wire:click="linkMessenger({{ $m->id }})"
                    class="shrink-0 text-[11px] font-semibold rounded-md px-2 py-0.5 border border-acc text-acc-tx hover:bg-acc-bg whitespace-nowrap">Прив'язати</button>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Поведінка --}}
    <div class="mt-4">
        <div class="text-[11px] uppercase tracking-[.06em] text-mut mb-1.5">Поведінка</div>

        <div class="flex items-center gap-2 mb-2">
            <span class="text-mut">Повернення:</span>
            <button wire:click="setReturnMode('auto')"
                class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ $slot->return_mode === 'auto' ? 'bg-acc text-white border-acc font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">Авто</button>
            <button wire:click="setReturnMode('sticky')"
                class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ $slot->return_mode === 'sticky' ? 'bg-acc text-white border-acc font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">Sticky</button>
        </div>

        <div class="flex items-center gap-2 mb-2">
            <span class="text-mut">Ручний режим:</span>
            @if($slot->pinned_number_entry_id)
                <span class="text-acc-tx font-medium">активний</span>
                <button wire:click="unpin" class="border border-acc text-acc-tx rounded-lg px-2.5 py-0.5 text-[11px] hover:bg-acc-bg">Зняти</button>
            @else
                <span class="text-mut">не активний</span>
            @endif
        </div>

        <div>
            <span class="text-mut block mb-1">Якщо всі впали:</span>
            <div class="flex gap-1.5 flex-wrap">
                @foreach(['hide' => 'Прибрати вивід', 'last' => 'Показувати останній', 'emergency' => 'Аварійний'] as $p => $lbl)
                    <button wire:click="setExhaustionPolicy('{{ $p }}')"
                        class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ $slot->exhaustion_policy === $p ? 'bg-acc text-white border-acc font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">{{ $lbl }}</button>
                @endforeach
            </div>
            @if($slot->exhaustion_policy === 'emergency')
            <div class="mt-2">
                <label class="text-mut text-[11px] block mb-1">Аварійний номер (показується коли всі впали):</label>
                <input type="text" wire:model="emergencyNumber"
                    placeholder="+380XXXXXXXXX"
                    class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:border-acc">
            </div>
            @endif
        </div>
    </div>

    {{-- Зберегти --}}
    <div class="mt-5 flex items-center gap-3">
        <button wire:click="save" class="bg-acc text-white rounded-lg px-4 py-1.5 font-semibold hover:opacity-90">Зберегти</button>
        <span class="text-[11px] text-mut">→ аудит + push</span>
    </div>

</div>
@else
<div class="h-full flex items-center justify-center p-6 text-center text-mut text-[12px]">Оберіть рядок, щоб редагувати</div>
@endif
</aside>
</div>
