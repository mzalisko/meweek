<div>
@if($open && $value && $slot)
<aside class="panel w-96 flex-shrink-0 border-l border-line bg-surface p-4 overflow-y-auto text-sm">

    {{-- Header --}}
    <div class="flex justify-between items-center mb-1">
        <span class="font-semibold text-ink">
            @svg('phone', 'ic ic-acc inline-block w-4 h-4 mr-1')
            Слот: {{ $value->key }}
        </span>
        <button wire:click="$set('open', false)" class="text-mut hover:text-ink transition-colors" aria-label="Закрити">✕</button>
    </div>

    @if($value->scope_type === 'site')
    <p class="text-xs text-mut mt-0.5 mb-3">Прив'язано до сайту #{{ $value->scope_id }}</p>
    @endif

    {{-- Гео-мітки --}}
    @if($value->geoTags->isNotEmpty())
    <div class="mb-3">
        <div class="text-xs font-semibold text-mut uppercase tracking-wide mb-1">Гео-мітки</div>
        <div class="flex flex-wrap gap-1">
            @foreach($value->geoTags as $geo)
                <span class="pill on text-xs px-2 py-0.5 rounded-full bg-acc/10 text-acc-tx border border-acc/20">{{ $geo->name }} ✓</span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Ланцюг номерів --}}
    <div class="mb-3">
        <div class="text-xs font-semibold text-mut uppercase tracking-wide mb-1">Ланцюг номерів</div>

        @foreach($entries as $i => $entry)
            @php
                $isFirst   = $i === 0;
                $isCurrent = $resolved && $resolved->entryId === $entry->id;
                $status    = $entry->phoneNumber->status ?? 'unknown';
                $e164      = $entry->phoneNumber->e164 ?? '—';
                $label     = $isFirst ? 'основний' : 'резерв';
            @endphp

            @php
                $isPinned = $slot->pinned_number_entry_id === $entry->id;
            @endphp
            <div class="chain flex items-center gap-2 py-1.5 border-b border-line/50 last:border-0 {{ $status !== 'active' ? 'opacity-60' : '' }}">
                {{-- Grip icon --}}
                @svg('grip', 'ic ic-sm inline-block w-3.5 h-3.5 text-mut flex-shrink-0')

                <span class="flex-1 min-w-0">
                    <b>#{{ $i }} {{ $label }}</b>
                    <span class="ml-2 font-mono text-xs text-ink">{{ $e164 }}</span>
                    @if($isPinned)
                        <span class="ml-1 text-xs text-acc-tx font-medium">📌</span>
                    @endif
                </span>

                <span class="flex-shrink-0 text-xs font-medium
                    @if($isCurrent) text-green-600
                    @elseif($status === 'active') text-blue-500
                    @else text-red-500
                    @endif
                ">
                    @if($isCurrent)
                        ● показується
                    @elseif($status === 'active')
                        active
                    @else
                        {{ $status }}
                    @endif
                </span>

                {{-- Pin button: show for active non-current entries --}}
                @if($status === 'active' && ! $isCurrent)
                    <button
                        wire:click="pin({{ $entry->id }})"
                        class="btn-ghost text-xs px-1.5 py-0.5 border border-line rounded hover:border-acc hover:text-acc-tx transition-colors flex-shrink-0"
                        title="Показувати цей номер"
                    >
                        Показувати цей
                    </button>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Прив'язані месенджери --}}
    <div class="mb-3">
        <div class="text-xs font-semibold text-mut uppercase tracking-wide mb-1">Прив'язані месенджери</div>

        @if($messengers->isEmpty())
            <p class="text-xs text-mut italic">— немає прив'язаних месенджерів</p>
        @else
            @foreach($messengers as $messenger)
                @php
                    $enabled = $messenger->content['enabled'] ?? true;
                    $network = $messenger->content['network'] ?? 'невідомо';
                @endphp
                <div class="flex items-center justify-between py-1.5 border-b border-line/50 last:border-0">
                    <span class="text-xs text-ink">{{ $network }}</span>
                    <button
                        wire:click="toggleMessenger({{ $messenger->id }})"
                        class="text-xs px-2 py-0.5 rounded border transition-colors
                            {{ $enabled
                                ? 'bg-green-50 text-green-700 border-green-300 hover:bg-red-50 hover:text-red-600 hover:border-red-300'
                                : 'bg-red-50 text-red-600 border-red-300 hover:bg-green-50 hover:text-green-700 hover:border-green-300' }}"
                        title="{{ $enabled ? 'Вимкнути месенджер' : 'Увімкнути месенджер' }}"
                    >
                        {{ $enabled ? 'Увімкнено' : 'Вимкнено' }}
                    </button>
                </div>
            @endforeach
        @endif
    </div>

    {{-- Поведінка --}}
    <div class="mb-3">
        <div class="text-xs font-semibold text-mut uppercase tracking-wide mb-1">Поведінка</div>

        {{-- Повернення: Авто / Sticky --}}
        <div class="text-xs mb-2">
            <span class="text-mut mr-1">Повернення:</span>
            <button
                wire:click="setReturnMode('auto')"
                class="px-2 py-0.5 rounded border text-xs transition-colors mr-1
                    {{ $slot->return_mode === 'auto'
                        ? 'bg-acc text-white border-acc font-semibold'
                        : 'border-line text-mut hover:border-acc hover:text-acc-tx' }}"
                title="Авто-повернення на пріоритетний номер"
            >
                Авто
            </button>
            <button
                wire:click="setReturnMode('sticky')"
                class="px-2 py-0.5 rounded border text-xs transition-colors
                    {{ $slot->return_mode === 'sticky'
                        ? 'bg-acc text-white border-acc font-semibold'
                        : 'border-line text-mut hover:border-acc hover:text-acc-tx' }}"
                title="Залишатись на поточному номері"
            >
                Sticky
            </button>
        </div>

        <div class="text-xs mb-2">
            Ручний режим:
            @if($slot->pinned_number_entry_id)
                <span class="text-acc-tx font-medium">активний</span>
                <button
                    wire:click="unpin"
                    class="btn-ghost text-xs ml-2 border border-line rounded px-1.5 py-0.5 hover:border-red-400 hover:text-red-600 transition-colors"
                    title="Зняти закріплення"
                >
                    Зняти
                </button>
            @else
                <span class="text-mut">не активний</span>
            @endif
        </div>

        {{-- Якщо всі впали: вибір політики вичерпання --}}
        <div class="text-xs">
            <span class="text-mut block mb-1">Якщо всі впали:</span>
            <div class="flex gap-1 flex-wrap">
                <button
                    wire:click="setExhaustionPolicy('hide')"
                    class="px-2 py-0.5 rounded border text-xs transition-colors
                        {{ $slot->exhaustion_policy === 'hide'
                            ? 'bg-acc text-white border-acc font-semibold'
                            : 'border-line text-mut hover:border-acc hover:text-acc-tx' }}"
                >
                    Прибрати вивід
                </button>
                <button
                    wire:click="setExhaustionPolicy('last')"
                    class="px-2 py-0.5 rounded border text-xs transition-colors
                        {{ $slot->exhaustion_policy === 'last'
                            ? 'bg-acc text-white border-acc font-semibold'
                            : 'border-line text-mut hover:border-acc hover:text-acc-tx' }}"
                >
                    Показувати останній
                </button>
                <button
                    wire:click="setExhaustionPolicy('emergency')"
                    class="px-2 py-0.5 rounded border text-xs transition-colors
                        {{ $slot->exhaustion_policy === 'emergency'
                            ? 'bg-acc text-white border-acc font-semibold'
                            : 'border-line text-mut hover:border-acc hover:text-acc-tx' }}"
                >
                    Аварійний
                </button>
            </div>
        </div>
    </div>

    {{-- Зберегти --}}
    <div class="mt-4 flex items-center gap-3">
        <button
            class="btn cursor-not-allowed opacity-60 text-sm px-4 py-1.5 rounded bg-acc text-white"
            disabled
            title="Зберегти — доступно після редагування (наступні таски)"
        >
            Зберегти
        </button>
        <span class="text-xs text-mut">→ аудит + push</span>
    </div>

</aside>
@else
<div></div>
@endif
</div>
