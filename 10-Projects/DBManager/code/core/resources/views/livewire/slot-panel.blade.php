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

            <div class="chain flex items-center gap-2 py-1.5 border-b border-line/50 last:border-0 {{ $status !== 'active' ? 'opacity-60' : '' }}">
                {{-- Grip icon --}}
                @svg('grip', 'ic ic-sm inline-block w-3.5 h-3.5 text-mut flex-shrink-0')

                <span class="flex-1 min-w-0">
                    <b>#{{ $i }} {{ $label }}</b>
                    <span class="ml-2 font-mono text-xs text-ink">{{ $e164 }}</span>
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
            </div>
        @endforeach
    </div>

    {{-- Прив'язані месенджери --}}
    <div class="mb-3">
        <div class="text-xs font-semibold text-mut uppercase tracking-wide mb-1">Прив'язані месенджери</div>
        <p class="text-xs text-mut italic">— (завантаження в наступному таску)</p>
    </div>

    {{-- Поведінка --}}
    <div class="mb-3">
        <div class="text-xs font-semibold text-mut uppercase tracking-wide mb-1">Поведінка</div>

        <div class="text-xs mb-1">
            Повернення:
            <span class="{{ $slot->return_mode === 'auto' ? 'font-semibold text-ink' : 'text-mut' }}">● Авто</span>
            <span class="{{ $slot->return_mode === 'sticky' ? 'font-semibold text-ink' : 'text-mut' }}">○ Sticky</span>
        </div>

        <div class="text-xs mb-1">
            Ручний режим:
            @if($slot->pinned_number_entry_id)
                <span class="text-acc-tx font-medium">активний</span>
                <button class="btn-ghost text-xs ml-2 cursor-not-allowed opacity-60" disabled>Зняти</button>
            @else
                <span class="text-mut">не активний</span>
            @endif
        </div>

        <div class="text-xs">
            Якщо всі впали:
            <span class="chip text-xs px-1.5 py-0.5 rounded bg-surface-2 border border-line">
                @php
                    $exhaustionLabels = ['hide' => 'Прибрати вивід', 'last' => 'Показати останній', 'emergency' => 'Аварійний номер'];
                @endphp
                {{ $exhaustionLabels[$slot->exhaustion_policy] ?? $slot->exhaustion_policy }} ▾
            </span>
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
