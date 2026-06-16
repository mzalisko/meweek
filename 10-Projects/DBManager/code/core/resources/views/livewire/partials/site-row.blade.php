<div @class([
    'flex items-center justify-between gap-3 border-b border-[#f1f3f1] px-3 py-2 last:border-b-0',
    'opacity-60' => $site->trashed(),
])>
    <div class="min-w-0">
        <div class="flex items-center gap-2">
            <span class="font-medium text-ink truncate">{{ $site->domain }}</span>
            @if($site->country_hint)
                <span class="rounded bg-[#eef1ee] px-1.5 py-0.5 text-[10px] text-mut">{{ $site->country_hint }}</span>
            @endif
            @if($site->trashed())
                <span class="rounded bg-[#f3e7e4] px-1.5 py-0.5 text-[10px] text-[#a85c52]">архів</span>
            @endif
        </div>
        <div class="text-[11px] text-mut truncate">{{ $site->name }}</div>
    </div>
    <div class="flex items-center gap-3 text-[11px] text-mut">
        <span>{{ $valueCounts[$site->id] ?? 0 }} значень</span>
    </div>
</div>
