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
    <div class="flex shrink-0 items-center gap-2">
        <span class="text-[11px] text-mut">{{ $valueCounts[$site->id] ?? 0 }} значень</span>
        @if($site->trashed())
            <button type="button" wire:click="restoreSite({{ $site->id }})"
                class="inline-flex h-7 items-center gap-1 rounded-md border border-[#dfe3e0] px-2 text-[11px] font-semibold text-acc-tx hover:border-acc hover:bg-acc-bg">
                @svg('check') Відновити
            </button>
        @else
            <a href="{{ route('admin.values', ['site' => $site->id]) }}" wire:navigate
                class="inline-flex h-7 items-center gap-1 rounded-md bg-acc px-2.5 text-[11px] font-semibold text-white hover:opacity-90">
                @svg('grid') Керувати даними
            </a>
            <button type="button" wire:click="editSite({{ $site->id }})" aria-label="Редагувати сайт"
                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-[#dfe3e0] text-mut hover:border-acc hover:bg-acc-bg hover:text-acc-tx">
                @svg('edit')
            </button>
            <button type="button" wire:click="archiveSite({{ $site->id }})"
                wire:confirm="Заархівувати сайт «{{ $site->domain }}»? Його дані лишаться, але сайт стане прихованим."
                aria-label="Заархівувати сайт"
                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-[#dfe3e0] text-mut hover:border-[#cdb9b4] hover:bg-[#f3e7e4] hover:text-[#a85c52]">
                @svg('trash')
            </button>
        @endif
    </div>
</div>
