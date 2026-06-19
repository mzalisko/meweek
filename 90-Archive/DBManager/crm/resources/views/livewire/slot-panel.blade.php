<div>
@if($open && $value && $slot)
    <div class="fixed inset-0 z-40" wire:click="close">
    <aside wire:click.stop wire:poll.15s="refreshEditLock" class="fixed right-0 top-0 bottom-0 w-[420px] max-w-[calc(100vw-24px)] bg-white border-l border-[#dfe3e0] shadow-[-18px_0_40px_rgba(28,34,30,0.12)] overflow-y-auto text-[13px]">
        <div class="p-4">
            <div class="flex justify-between items-start gap-3">
                <div class="min-w-0">
                    @if($mode === 'number')
                        @php
                            $editingEntry = $entries->firstWhere('id', $editingEntryId);
                            $editingPriority = $editingEntry?->priority ?? 0;
                            $numberLabel = $editingPriority === 0 ? '#1 основний' : '#1.' . $editingPriority . ' резерв';
                            $numberStatus = $editingEntry?->phoneNumber?->status ?? 'active';
                            $isPinnedNumber = $editingEntry && $slot->pinned_number_entry_id === $editingEntry->id;
                        @endphp
                        <b class="text-acc-tx flex items-center gap-1.5">@svg('phone') {{ $numberLabel }}</b>
                        <div class="text-[11px] text-mut mt-1 truncate">{{ $value->key }}</div>
                    @else
                        <b class="text-acc-tx flex items-center gap-1.5">@svg('phone') Слот: {{ $value->key }}</b>
                        <div class="text-[11px] text-mut mt-1 flex items-center gap-1">
                    @if($value->scope_type === 'site')<span class="text-acc-tx">@svg('edit')</span> Перекриття цього сайта @else Значення групи @endif
                        </div>
                    @endif
                </div>
                <button wire:click="close" class="text-mut hover:text-ink shrink-0" aria-label="Закрити">@svg('x')</button>
            </div>

            @include('livewire.partials.edit-lock-alert')

            @if(!$editLockBlocked)
            @if($mode === 'settings')
                @php
                    $resolvedState = $resolved?->state ?? null;
                    $resolvedNumber = $resolved?->number ?? null;
                @endphp
                @if($resolvedState === 'exhausted')
                    <div class="mt-4 rounded-[10px] border border-bad-tx/30 bg-bad-bg px-3 py-2 text-[12px]">
                        <div class="flex items-center gap-2 text-bad-tx font-medium">@svg('alert') Всі номери вичерпано</div>
                        <div class="mt-1 text-mut">
                            @if($slot->exhaustion_policy === 'emergency')
                                Зараз показується аварійний номер:
                                <span class="font-mono text-ink">{{ $resolvedNumber ?? '—' }}</span>
                            @elseif($slot->exhaustion_policy === 'last')
                                Зараз показується останній активний номер:
                                <span class="font-mono text-ink">{{ $resolvedNumber ?? '—' }}</span>
                            @else
                                Вивід приховано.
                            @endif
                        </div>
                    </div>
                @endif
            @endif

            @if(($value->status ?? 'active') === 'hidden')
                <div class="mt-4 rounded-[10px] border border-bad-tx/30 bg-bad-bg px-3 py-2 text-[12px]">
                    <div class="flex items-center gap-2 text-bad-tx font-medium">@svg('eye-off') Слот приховано</div>
                    <div class="mt-1 text-mut">У публікації цей слот не віддається. Номер і резерви залишаються в адмінці.</div>
                </div>
            @endif

            @if($mode === 'number')
                @if($editingEntry)
                    <div class="mt-5">
                        <label class="text-[11px] uppercase tracking-[.06em] text-mut block mb-1.5">Номер</label>
                        <input type="text"
                            wire:model="editE164"
                            placeholder="+380441112233"
                            class="w-full border border-[#dfe3e0] rounded-lg px-3 py-2 text-[13px] focus:outline-none focus:border-acc">
                        @error('editE164')<p class="mt-1 text-[11px] text-bad-tx">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-4">
                        <div class="text-[11px] uppercase tracking-[.06em] text-mut mb-1.5">Дії</div>
                        <div class="flex flex-wrap gap-1.5">
                            @if($numberStatus === 'down')
                                <button wire:click="setNumberStatus({{ $editingEntry->id }}, 'active')"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] border border-ok-tx/50 text-ok-tx hover:bg-ok-bg">@svg('check') Повернути</button>
                            @else
                                <button wire:click="setNumberStatus({{ $editingEntry->id }}, 'down')" wire:confirm="Приховати номер і позначити його неактивним?"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] border border-bad-tx/30 text-bad-tx hover:bg-bad-bg">@svg('eye-off') Приховати</button>
                            @endif

                            @if($isPinnedNumber)
                                <button wire:click="unpin"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] border border-acc text-acc-tx bg-acc-bg">@svg('pin') Закріплено</button>
                            @elseif($numberStatus === 'active')
                                <button wire:click="pin({{ $editingEntry->id }})"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] border border-acc text-acc-tx hover:bg-acc-bg">@svg('pin') Показувати цей</button>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 flex items-center gap-2">
                        <button wire:click="saveNumber" class="bg-acc text-white rounded-lg px-4 py-1.5 font-semibold hover:opacity-90">Зберегти</button>
                        <button wire:click="close" class="border border-[#dfe3e0] text-mut rounded-lg px-3 py-1.5 hover:border-acc hover:text-acc-tx">Відхилити</button>
                        <button wire:click="removeNumber({{ $editingEntry->id }})" wire:confirm="Видалити цей номер із ланцюга?"
                            class="ml-auto text-bad-tx hover:opacity-80 px-1.5 py-1" title="Видалити" aria-label="Видалити">@svg('trash')</button>
                    </div>
                @else
                    <div class="mt-5 rounded-lg border border-bad-tx/30 bg-bad-bg px-3 py-2 text-bad-tx text-[12px]">Номер не знайдено.</div>
                @endif
            @else
                <div class="mt-4">
                    <div class="text-[11px] uppercase tracking-[.06em] text-mut mb-1.5">Ключ слота</div>
                    <div class="flex items-center gap-2">
                        <input type="text"
                            wire:model="slotKey"
                            placeholder="phone_ua_1"
                            class="flex-1 min-w-0 border border-[#dfe3e0] rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:border-acc">
                        <button wire:click="renameSlot"
                            class="shrink-0 inline-flex items-center gap-1 border border-acc text-acc-tx rounded-lg px-3 py-1.5 text-[12px] font-semibold hover:bg-acc-bg whitespace-nowrap">@svg('check') перейменувати</button>
                    </div>
                    <p class="mt-1 text-[11px] text-mut">Латиниця, цифри, підкреслення. Перейменування оновлює перекриття сайтів і прив'язки месенджерів.</p>
                    @error('slotKey')<p class="mt-1 text-[11px] text-bad-tx">{{ $message }}</p>@enderror
                </div>

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
                    <p class="text-[11px] text-mut mb-1.5">Резерв успадковує гео-мітки основного слота.</p>
                    <div class="flex items-center gap-2">
                        <input type="text"
                            wire:model="newNumber"
                            placeholder="+380441112233"
                            class="flex-1 min-w-0 border border-[#dfe3e0] rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:border-acc">
                        <button wire:click="addNumber"
                            class="shrink-0 inline-flex items-center gap-1 bg-acc text-white rounded-lg px-3 py-1.5 text-[12px] font-semibold hover:opacity-90 whitespace-nowrap">@svg('plus') додати</button>
                    </div>
                    @error('newNumber')<p class="mt-1 text-[11px] text-bad-tx">{{ $message }}</p>@enderror
                </div>

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
                            <label class="text-mut text-[11px] block mb-1">Аварійний номер:</label>
                            <input type="text" wire:model.live.debounce.500ms="emergencyNumber"
                                placeholder="+380XXXXXXXXX"
                                class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 text-[13px] focus:outline-none focus:border-acc">
                        </div>
                        @endif
                    </div>


                </div>

                <div class="mt-5 flex items-center gap-3">
                    <button wire:click="close" class="border border-[#dfe3e0] text-mut rounded-lg px-3 py-1.5 hover:border-acc hover:text-acc-tx">Закрити</button>
                    <span class="text-[11px] text-mut">зберігається автоматично</span>
                </div>
            @endif
            @endif
        </div>
    </aside>
    </div>
@endif

</div>
