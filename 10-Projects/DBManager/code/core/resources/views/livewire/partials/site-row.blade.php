<div @class([
    'flex items-center justify-between gap-3 border-b border-[#f1f3f1] py-2 px-3 last:border-b-0',
    'opacity-60' => $site->trashed(),
    'bg-[#fafbfa]' => ($depth ?? 0) > 0,
]) data-site-row="{{ $site->id }}">
    <div class="min-w-0 flex-1 grid items-center gap-x-2 gap-y-0.5"
        style="grid-template-columns: 1.5rem minmax(10rem, 14rem) 1.25rem auto auto auto; padding-left: {{ ($depth ?? 0) * 20 }}px;">
        @if(($depth ?? 0) > 0)
            <span class="text-mut font-normal select-none">↳</span>
        @else
            <span></span>
        @endif

        <span class="font-medium text-ink truncate">{{ $site->domain }}</span>

        @if(!$site->trashed())
            <button type="button" wire:click="toggleFavorite('site', {{ $site->id }})"
                data-site-favorite="{{ $site->id }}"
                class="{{ in_array($site->id, $favSiteIds) ? 'text-yellow-500' : 'text-gray-300 hover:text-yellow-500' }} text-[14px] justify-self-center focus:outline-none transition-colors"
                title="{{ in_array($site->id, $favSiteIds) ? 'Вилучити з улюблених' : 'Додати до улюблених' }}">
                {{ in_array($site->id, $favSiteIds) ? '★' : '☆' }}
            </button>
        @else
            <span></span>
        @endif

        <span class="text-mut text-[10px] whitespace-nowrap">ID: {{ $site->id }}</span>

        @if($site->country_hint)
            <span class="rounded bg-[#eef1ee] px-1.5 py-0.5 text-[10px] text-mut justify-self-start">{{ $site->country_hint }}</span>
        @endif

        @if($site->parent_site_id)
            <span class="rounded bg-[#e8efff] px-1.5 py-0.5 text-[10px] text-[#3b66c4] font-medium justify-self-start">сателіт</span>
        @endif

        @if($site->trashed())
            <span class="rounded bg-[#f3e7e4] px-1.5 py-0.5 text-[10px] text-[#a85c52] justify-self-start">архів</span>
        @endif

        <div class="col-start-2 col-span-5 row-start-2 text-[11px] text-mut truncate">{{ $site->name }}</div>
    </div>

    <div class="flex shrink-0 items-center gap-2">
        <span class="text-[11px] text-mut">{{ $valueCounts[$site->id] ?? 0 }} значень</span>
        @if($site->trashed() && $canManageSites)
            <button type="button" wire:click="restoreSite({{ $site->id }})"
                class="inline-flex h-7 items-center gap-1 rounded-md border border-[#dfe3e0] px-2 text-[11px] font-semibold text-acc-tx hover:border-acc hover:bg-acc-bg">
                @svg('check') Відновити
            </button>
        @elseif(! $site->trashed())
            <a href="{{ route('admin.site', ['site' => $site->id]) }}" wire:navigate
                class="inline-flex h-7 items-center gap-1 rounded-md bg-acc px-2.5 text-[11px] font-semibold text-white hover:opacity-90">
                @svg('grid') Керувати даними
            </a>
            @if($canManageSites)
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
        @endif
    </div>
</div>
