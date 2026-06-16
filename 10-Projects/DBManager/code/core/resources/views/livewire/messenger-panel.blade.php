<div>
@if($open && $value)
    <div class="fixed inset-0 z-40" wire:click="close">
    <aside wire:click.stop class="fixed right-0 top-0 bottom-0 w-[420px] max-w-[calc(100vw-24px)] bg-white border-l border-[#dfe3e0] shadow-[-18px_0_40px_rgba(28,34,30,0.12)] overflow-y-auto text-[13px]">
        <div class="p-4">
            <div class="flex justify-between items-start gap-3">
                <div class="min-w-0">
                    <b class="text-acc-tx flex items-center gap-1.5">@svg('msg') Месенджер: {{ $value->content['messenger_slot'] ?? $value->key }}</b>
                    <div class="text-[11px] text-mut mt-1">
                        @if($value->scope_type === 'site')<span class="text-acc-tx">@svg('edit')</span> Перекриття цього сайту @else Значення групи @endif
                    </div>
                </div>
                <button wire:click="close" class="text-mut hover:text-ink shrink-0" aria-label="Закрити">@svg('x')</button>
            </div>

            @if($allHidden)
                <div class="mt-4 rounded-[10px] border border-bad-tx/30 bg-bad-bg px-3 py-2 text-[12px]">
                    <div class="flex items-center gap-2 text-bad-tx font-medium">@svg('eye-off') Слот месенджера приховано</div>
                    <div class="mt-1 text-mut">У публікації не віддається ні основний месенджер, ні його резерви.</div>
                </div>
            @endif

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
                    <p class="mt-1 text-[11px] text-bad-tx">Вибери хоча б одну гео-мітку.</p>
                @endif
            </div>

            <div class="mt-4">
                <div class="text-[11px] uppercase tracking-[.06em] text-mut mb-1.5">Додати резерв</div>
                <p class="text-[11px] text-mut mb-1.5">Резерв успадковує мережу і гео-мітки основного месенджера.</p>
                <div class="flex items-center gap-2">
                    <input type="text"
                        wire:model="newValue"
                        placeholder="посилання, номер або код"
                        class="flex-1 min-w-0 border border-[#dfe3e0] rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:border-acc">
                    <button wire:click="addReserve"
                        class="shrink-0 inline-flex items-center gap-1 bg-acc text-white rounded-lg px-3 py-1.5 text-[12px] font-semibold hover:opacity-90 whitespace-nowrap">@svg('plus') додати</button>
                </div>
                @error('newValue')<p class="mt-1 text-[11px] text-bad-tx">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4">
                <div class="text-[11px] uppercase tracking-[.06em] text-mut mb-1.5">Поведінка</div>

                <div class="flex items-center gap-2 mb-2">
                    <span class="text-mut">Повернення:</span>
                    <button wire:click="setReturnMode('auto')"
                        class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ $returnMode === 'auto' ? 'bg-acc text-white border-acc font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">Авто</button>
                    <button wire:click="setReturnMode('sticky')"
                        class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ $returnMode === 'sticky' ? 'bg-acc text-white border-acc font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">Sticky</button>
                </div>

                <div>
                    <span class="text-mut block mb-1">Якщо всі впали:</span>
                    <div class="flex gap-1.5 flex-wrap">
                        <button wire:click="setExhaustionPolicy('hide')"
                            class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ $policy === 'hide' ? 'bg-acc text-white border-acc font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">Прибрати вивід</button>
                        <button wire:click="setExhaustionPolicy('last')"
                            class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ $policy === 'last' ? 'bg-acc text-white border-acc font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">Показувати останній</button>
                        <button wire:click="setExhaustionPolicy('emergency')"
                            class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ $policy === 'emergency' ? 'bg-acc text-white border-acc font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">Аварійний</button>
                    </div>
                    @if($policy === 'emergency')
                    <div class="mt-2">
                        <label class="text-mut text-[11px] block mb-1">Аварійне значення:</label>
                        <input type="text" wire:model.live.debounce.500ms="emergencyValue"
                            placeholder="посилання, номер або код"
                            class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:border-acc">
                    </div>
                    @endif
                </div>

                <div class="mt-3">
                    <span class="text-mut block mb-1">Дії слоту:</span>
                    <div class="flex gap-1.5 flex-wrap">
                        <button wire:click="showSlot"
                            class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ ! $allHidden ? 'bg-acc text-white border-acc font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">Показати слот</button>
                        <button wire:click="hideSlot"
                            class="rounded-lg px-2.5 py-0.5 text-[11px] border {{ $allHidden ? 'bg-bad-tx text-white border-bad-tx font-semibold' : 'border-[#dfe3e0] text-mut hover:border-bad-tx' }}">Приховати слот</button>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3">
                <button wire:click="close" class="border border-[#dfe3e0] text-mut rounded-lg px-3 py-1.5 hover:border-acc hover:text-acc-tx">Закрити</button>
                <span class="text-[11px] text-mut">зберігається автоматично</span>
            </div>
        </div>
    </aside>
    </div>
@endif
</div>
