@if($editLockState && ($editLockState['has_takeover_request'] ?? false))
    <div class="mt-4 rounded-lg border border-warn-tx/40 bg-warn-bg px-3 py-2 text-[12px] text-warn-tx">
        <div class="flex items-start gap-2">
            <span class="mt-0.5">@svg('alert')</span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold">
                    {{ $editLockState['takeover_requester_name'] ?? 'Інший користувач' }} просить доступ до редагування
                </div>
                <div class="mt-0.5 text-ink/70">
                    Запис зараз відкритий у тебе. Дозволь перехоплення, якщо вже не редагуєш його, або відхили запит і продовжуй роботу.
                </div>
            </div>
        </div>
        <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" wire:click="approveEditLockTakeover"
                class="inline-flex items-center gap-1 rounded-lg border border-ok-tx/30 bg-white px-2.5 py-1 text-[11px] font-semibold text-ok-tx hover:bg-ok-bg">
                @svg('check') Дозволити
            </button>
            <button type="button" wire:click="rejectEditLockTakeover"
                class="inline-flex items-center gap-1 rounded-lg border border-warn-tx/40 bg-white px-2.5 py-1 text-[11px] font-semibold text-warn-tx hover:bg-[#fff8e8]">
                @svg('x') Відхилити
            </button>
        </div>
    </div>
@elseif($editLockBlocked && $editLockState)
    <div class="mt-4 rounded-lg border border-warn-tx/40 bg-warn-bg px-3 py-2 text-[12px] text-warn-tx">
        <div class="flex items-start gap-2">
            <span class="mt-0.5">@svg('alert')</span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold">Запис редагує {{ $editLockState['owner_name'] ?? 'інший користувач' }}</div>
                <div class="mt-0.5 text-ink/70">
                    @if($editLockState['takeover_request_pending'] ?? false)
                        Запит на перехоплення надіслано. Очікуємо, поки поточний редактор дозволить або відхилить доступ.
                    @elseif($editLockState['takeover_request_denied'] ?? false)
                        Запит відхилено{{ !empty($editLockState['takeover_denied_by_name']) ? ' користувачем ' . $editLockState['takeover_denied_by_name'] : '' }}. Редагування лишається заблокованим.
                    @else
                        Щоб не перезаписати чужі зміни, редагування заблоковано. Можна попросити поточного редактора передати доступ.
                    @endif
                </div>
            </div>
        </div>
        @unless($editLockState['takeover_request_pending'] ?? false)
            <button type="button" wire:click="requestEditLockTakeover"
                class="mt-2 inline-flex items-center gap-1 rounded-lg border border-warn-tx/40 bg-white px-2.5 py-1 text-[11px] font-semibold text-warn-tx hover:bg-[#fff8e8]">
                @svg('edit') Запросити перехоплення
            </button>
        @endunless
    </div>
@elseif($editLockState && ($editLockState['taken_over'] ?? false))
    <div class="mt-4 rounded-lg border border-acc-bd bg-acc-bg px-3 py-2 text-[12px] text-acc-tx">
        <div class="flex items-center gap-2 font-semibold">
            @svg('check') Редагування передано тобі{{ !empty($editLockState['takeover_approved_by_name']) ? ' користувачем ' . $editLockState['takeover_approved_by_name'] : '' }}
        </div>
    </div>
@endif
