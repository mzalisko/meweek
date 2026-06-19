<div>
@if($open)
<aside wire:poll.15s="refreshEditLock" class="fixed top-0 right-0 bottom-0 z-40 w-[420px] bg-white border-l border-[#e3e5e1] shadow-xl overflow-y-auto text-[13px]">
<div class="p-4">

    {{-- Header --}}
    <div class="flex justify-between items-center mb-4">
        <b class="text-acc-tx text-[15px] flex items-center gap-1.5">@svg('edit') {{ $valueId ? 'Редагувати значення' : 'Додати значення' }}</b>
        <button wire:click="closePanel" class="text-mut hover:text-ink" aria-label="Закрити">@svg('x')</button>
    </div>

    @include('livewire.partials.edit-lock-alert')

    @if(!$editLockBlocked)
    {{-- Type --}}
    <div class="mb-3">
        <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Тип</label>
        <select wire:model.live="type" class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:border-acc">
            <option value="phone">Телефон</option>
            <option value="messenger">Месенджер</option>
            <option value="text">Текст</option>
            <option value="price">Ціна</option>
            <option value="address">Адреса</option>
            <option value="social">Соцмережа</option>
        </select>
        @error('type')<p class="text-bad-tx text-[11px] mt-0.5">{{ $message }}</p>@enderror
    </div>

    {{-- Key --}}
    <div class="mb-3">
        <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Ключ</label>
        <input wire:model="key" type="text" placeholder="price_basic" class="w-full border rounded-lg px-3 py-1.5 focus:outline-none focus:border-acc @error('key') border-bad-tx @else border-[#dfe3e0] @enderror" />
        @error('key')<p class="text-bad-tx text-[11px] mt-0.5">{{ $message }}</p>@enderror
    </div>

    {{-- Value (не для телефона — там номери в панелі слота; не для цін — там список) --}}
    @if($type !== 'phone' && $type !== 'price')
    <div class="mb-3">
        <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">
            {{ $type === 'messenger' ? 'Значення месенджера' : 'Значення' }}
        </label>
        <input wire:model="value" type="text" class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 focus:outline-none focus:border-acc @error('value') border-bad-tx @enderror" />
        @error('value')<p class="text-bad-tx text-[11px] mt-0.5">{{ $message }}</p>@enderror
    </div>
    @elseif($type === 'phone')
    <p class="mb-3 text-[12px] text-mut bg-[#fafbfa] border border-[#e3e5e1] rounded-lg px-3 py-2">Номери додаються в панелі слота після створення.</p>
    @endif

    {{-- Price List editor --}}
    @if($type === 'price')
    <div class="mb-4">
        <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1.5">Список цін у слоті</label>
        <div class="space-y-3">
            @foreach($prices as $index => $price)
                <div class="p-3 bg-[#fafbfa] border border-[#dfe3e0] rounded-lg relative space-y-2.5">
                    {{-- Remove Button --}}
                    @if(count($prices) > 1)
                        <button type="button" wire:click="removePrice({{ $index }})" 
                            class="absolute top-2 right-2 text-mut hover:text-bad-tx text-xs font-bold" 
                            title="Видалити ціну" aria-label="Видалити ціну">
                            &times;
                        </button>
                    @endif
                    
                    {{-- Label input --}}
                    <div>
                        <label class="block text-mut text-[10px] uppercase mb-0.5">Опис / Назва (напр. Україна)</label>
                        <input type="text" wire:model="prices.{{ $index }}.label" placeholder="Україна" 
                            class="w-full border border-[#dfe3e0] rounded-lg px-2.5 py-1 text-xs focus:outline-none focus:border-acc" />
                    </div>

                    {{-- Value input --}}
                    <div>
                        <label class="block text-mut text-[10px] uppercase mb-0.5">Значення / Ціна (напр. 1200)</label>
                        <input type="text" wire:model="prices.{{ $index }}.value" placeholder="1200" 
                            class="w-full border border-[#dfe3e0] rounded-lg px-2.5 py-1 text-xs focus:outline-none focus:border-acc" />
                        @error("prices.{$index}.value")
                            <p class="text-bad-tx text-[11px] mt-0.5">Ціна є обов'язковою.</p>
                        @enderror
                    </div>

                    {{-- Geo tags checkboxes per price entry --}}
                    <div>
                        <span class="block text-mut text-[10px] uppercase mb-1">Гео-видимість</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($allGeoTags as $gt)
                                <label class="cursor-pointer" wire:key="price-{{ $index }}-geo-{{ $gt->code }}">
                                    <input type="checkbox" wire:model="prices.{{ $index }}.geo" value="{{ $gt->code }}" class="peer sr-only">
                                    <span class="inline-flex items-center gap-1 border rounded-md px-1.5 py-0.5 text-[10px] transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-acc/30 peer-checked:border-acc peer-checked:bg-acc-bg peer-checked:text-acc-tx peer-checked:font-semibold
                                        {{ in_array($gt->code, $prices[$index]['geo'] ?? []) ? 'border-acc bg-acc-bg text-acc-tx font-semibold' : 'border-[#dfe3e0] text-mut hover:border-acc' }}">
                                        {{ $gt->code }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
        <button type="button" wire:click="addPrice" 
            class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-acc hover:text-acc-tx">
            + Додати ціну
        </button>
    </div>
    @endif

    {{-- Messenger-specific fields --}}
    @if($type === 'messenger')
    <div class="mb-3">
        <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Мережа</label>
        <input wire:model="network" type="text" placeholder="telegram" class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 focus:outline-none focus:border-acc" />
        @error('network')<p class="text-bad-tx text-[11px] mt-0.5">{{ $message }}</p>@enderror
    </div>
    @endif

    {{-- Гео-мітки (для цін вони налаштовуються всередині кожної ціни) --}}
    @if($allGeoTags->isNotEmpty() && $type !== 'price')
    <div class="mb-4">
        <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1.5">Гео-мітки</label>
        <div class="flex flex-wrap gap-1.5">
            @foreach($allGeoTags as $gt)
                <label class="flex items-center gap-1.5 cursor-pointer border rounded-[9px] px-2.5 py-1 text-[11px] transition-colors
                    {{ in_array($gt->id, $geoTagIds) ? 'border-acc bg-acc-bg text-acc-tx font-semibold' : 'border-[#dfe3e0] text-mut' }}">
                    <input type="checkbox" wire:model.live="geoTagIds" value="{{ $gt->id }}" class="sr-only">
                    {{ $gt->code }}
                </label>
            @endforeach
        </div>
    </div>
    @endif



    {{-- Buttons --}}
    <div class="flex justify-between gap-2 items-center">
        @if($valueId)
        <button wire:click="delete" wire:confirm="Видалити це значення?" type="button" class="px-3 py-1.5 rounded-lg border border-bad-tx/40 text-bad-tx hover:bg-bad-bg">Видалити</button>
        @else
        <span></span>
        @endif
        <div class="flex gap-2">
            <button wire:click="closePanel" type="button" class="px-4 py-1.5 rounded-lg border border-[#dfe3e0] text-mut hover:bg-[#f5f5f3]">Скасувати</button>
            <button wire:click="save" type="button" class="px-4 py-1.5 rounded-lg bg-acc text-white font-semibold hover:opacity-90">Зберегти</button>
        </div>
    </div>
    @endif

</div>
</aside>
@endif

</div>
