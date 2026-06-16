<x-slot name="breadcrumb">
    <span class="text-mut text-xs">›</span>
    <span class="text-xs font-semibold text-ink">Сайти і групи</span>
</x-slot>

<x-slot name="context">
    <div class="bg-acc-bg border-b border-acc-bd px-[18px] py-2 text-xs text-acc-tx flex gap-3 items-center">
        @svg('sites')
        <span>Групи, сайти, токени і зв’язок. Звідси — вхід у дані кожного сайта.</span>
    </div>
</x-slot>

<div class="relative h-full w-full p-4">
    <div class="mb-3 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-[18px] font-semibold text-acc-tx">Сайти і групи</h1>
            <p class="mt-0.5 text-[12px] text-mut">Створюйте групи й сайти, керуйте токенами та заходьте в дані сайта.</p>
        </div>
        <label class="inline-flex items-center gap-2 text-xs text-mut">
            <input type="checkbox" wire:model.live="showArchived" class="rounded border-[#cfd6d0]">
            Показати архів
        </label>
    </div>

    <div class="space-y-4">
        @forelse($groups as $group)
            <div @class([
                'rounded-lg border bg-white',
                'border-[#dfe3e0]' => ! $group->trashed(),
                'border-dashed border-[#cdb9b4]' => $group->trashed(),
            ])>
                <div class="flex items-center justify-between gap-2 border-b border-[#edf0ed] px-3 py-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-semibold text-ink truncate">{{ $group->name }}</span>
                        @if($group->trashed())
                            <span class="rounded bg-[#f3e7e4] px-1.5 py-0.5 text-[10px] text-[#a85c52]">архів</span>
                        @endif
                        <span class="text-[11px] text-mut">{{ $group->sites->count() }} сайт(ів)</span>
                    </div>
                </div>
                <div>
                    @forelse($group->sites as $site)
                        @include('livewire.partials.site-row', ['site' => $site])
                    @empty
                        <div class="px-3 py-2 text-[11px] text-mut">Немає сайтів у групі.</div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-[#dfe3e0] px-3 py-6 text-center text-xs text-mut">
                Ще немає груп.
            </div>
        @endforelse

        @if($ungroupedSites->isNotEmpty())
            <div class="rounded-lg border border-[#dfe3e0] bg-white">
                <div class="border-b border-[#edf0ed] px-3 py-2 font-semibold text-ink">Без групи</div>
                <div>
                    @foreach($ungroupedSites as $site)
                        @include('livewire.partials.site-row', ['site' => $site])
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
