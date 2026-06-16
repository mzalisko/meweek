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
        <div class="flex items-center gap-3">
            <label class="inline-flex items-center gap-2 text-xs text-mut">
                <input type="checkbox" wire:model.live="showArchived" class="rounded border-[#cfd6d0]">
                Показати архів
            </label>
            <button type="button" wire:click="startCreateGroup"
                class="inline-flex items-center gap-1.5 rounded-lg bg-acc px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                @svg('plus') Створити групу
            </button>
        </div>
    </div>

    <div class="space-y-4">
        @forelse($groups as $group)
            <div wire:key="group-{{ $group->id }}" @class([
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
                    <div class="flex shrink-0 items-center gap-1.5">
                        @if($group->trashed())
                            <button type="button" wire:click="restoreGroup({{ $group->id }})"
                                class="inline-flex h-7 items-center gap-1 rounded-md border border-[#dfe3e0] px-2 text-[11px] font-semibold text-acc-tx hover:border-acc hover:bg-acc-bg">
                                @svg('check') Відновити
                            </button>
                        @else
                            <button type="button" wire:click="editGroup({{ $group->id }})" aria-label="Редагувати групу"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-[#dfe3e0] text-mut hover:border-acc hover:bg-acc-bg hover:text-acc-tx">
                                @svg('edit')
                            </button>
                            <button type="button" wire:click="archiveGroup({{ $group->id }})"
                                wire:confirm="Заархівувати групу «{{ $group->name }}» разом з усіма її сайтами? Дані лишаться, сайти стануть прихованими."
                                aria-label="Заархівувати групу"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-[#dfe3e0] text-mut hover:border-[#cdb9b4] hover:bg-[#f3e7e4] hover:text-[#a85c52]">
                                @svg('trash')
                            </button>
                        @endif
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
                Ще немає груп. Натисніть «Створити групу», щоб почати.
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

    @if($panelMode === 'group')
        <div class="fixed inset-0 z-20 bg-[rgba(20,26,22,0.28)]" wire:click="closePanel"></div>
        <aside wire:click.stop
            class="fixed right-0 top-0 bottom-0 z-30 w-[460px] max-w-[calc(100vw-24px)] overflow-y-auto border-l border-[#dfe3e0] bg-white text-[13px] shadow-[-18px_0_40px_rgba(28,34,30,0.12)]">
            <div class="flex items-center justify-between border-b border-[#edf0ed] px-4 py-3">
                <h2 class="text-[15px] font-semibold text-acc-tx">
                    {{ $editingGroupId ? 'Редагувати групу' : 'Нова група' }}
                </h2>
                <button type="button" wire:click="closePanel" class="text-mut hover:text-ink shrink-0" aria-label="Закрити">@svg('x')</button>
            </div>

            <form wire:submit="saveGroup" class="space-y-4 px-4 py-4">
                <div>
                    <label class="mb-1 block text-[11px] uppercase tracking-wide text-mut">Назва групи</label>
                    <input wire:model="groupName" type="text" autofocus
                        class="w-full rounded-lg border border-[#dfe3e0] px-3 py-2 focus:border-acc focus:outline-none">
                    @error('groupName')
                        <p class="mt-1 text-[12px] text-bad-tx">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-acc px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                        Зберегти
                    </button>
                    <button type="button" wire:click="closePanel"
                        class="rounded-lg border border-[#dfe3e0] px-3 py-2 text-xs font-semibold text-mut hover:border-acc hover:text-acc-tx">
                        Скасувати
                    </button>
                </div>
            </form>
        </aside>
    @endif
</div>
