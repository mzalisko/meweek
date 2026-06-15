<div>
@if($open)
<div class="fixed inset-0 z-40 flex items-center justify-center bg-ink/30 p-4">
    <div class="w-full max-w-md bg-white border border-[#e3e5e1] rounded-xl shadow-lg p-6 text-[13px]">

        {{-- Header --}}
        <div class="flex justify-between items-center mb-4">
            <b class="text-acc-tx text-[15px]">{{ $valueId ? 'Редагувати значення' : 'Додати значення' }}</b>
            <button wire:click="$set('open', false)" class="text-mut hover:text-ink" aria-label="Закрити">@svg('x')</button>
        </div>

        {{-- Type --}}
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Тип</label>
            <select wire:model.live="type" class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:border-acc">
                <option value="text">Текст</option>
                <option value="price">Ціна</option>
                <option value="messenger">Месенджер</option>
                <option value="address">Адреса</option>
                <option value="social">Соцмережа</option>
                <option value="phone">Телефон</option>
            </select>
            @error('type')<p class="text-bad-tx text-[11px] mt-0.5">{{ $message }}</p>@enderror
        </div>

        {{-- Key --}}
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Ключ</label>
            <input wire:model="key" type="text" placeholder="price_basic" class="w-full border rounded-lg px-3 py-1.5 focus:outline-none focus:border-acc @error('key') border-bad-tx @else border-[#dfe3e0] @enderror" />
            @error('key')<p class="text-bad-tx text-[11px] mt-0.5">{{ $message }}</p>@enderror
        </div>

        {{-- Value (не для телефона — там номери в панелі слота) --}}
        @if($type !== 'phone')
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Значення</label>
            <input wire:model="value" type="text" class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 focus:outline-none focus:border-acc @error('value') border-bad-tx @enderror" />
            @error('value')<p class="text-bad-tx text-[11px] mt-0.5">{{ $message }}</p>@enderror
        </div>
        @else
        <p class="mb-3 text-[12px] text-mut bg-[#fafbfa] border border-[#e3e5e1] rounded-lg px-3 py-2">Номери додаються в панелі слота після створення.</p>
        @endif

        {{-- Messenger-specific fields --}}
        @if($type === 'messenger')
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Мережа</label>
            <input wire:model="network" type="text" placeholder="telegram" class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 focus:outline-none focus:border-acc" />
        </div>
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">URL</label>
            <input wire:model="url" type="text" placeholder="https://t.me/example" class="w-full border border-[#dfe3e0] rounded-lg px-3 py-1.5 focus:outline-none focus:border-acc" />
        </div>
        @endif

        {{-- Scope --}}
        <div class="mb-4">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1.5">Область дії</label>
            <div class="flex gap-3">
                <label class="flex-1 flex items-center gap-2 cursor-pointer border rounded-lg px-3 py-1.5 {{ $scope === 'site' ? 'border-acc bg-acc-bg text-acc-tx font-semibold' : 'border-[#dfe3e0] text-mut' }}">
                    <input wire:model.live="scope" type="radio" value="site" class="accent-[#54708c]" />
                    Цей сайт
                </label>
                <label class="flex-1 flex items-center gap-2 cursor-pointer border rounded-lg px-3 py-1.5 {{ $scope === 'group' ? 'border-acc bg-acc-bg text-acc-tx font-semibold' : 'border-[#dfe3e0] text-mut' }}">
                    <input wire:model.live="scope" type="radio" value="group" class="accent-[#54708c]" />
                    Уся група
                </label>
            </div>
            @error('scope')<p class="text-bad-tx text-[11px] mt-0.5">{{ $message }}</p>@enderror
        </div>

        {{-- Buttons --}}
        <div class="flex justify-between gap-2 items-center">
            @if($valueId)
            <button wire:click="delete" wire:confirm="Видалити це значення?" type="button" class="px-3 py-1.5 rounded-lg border border-bad-tx/40 text-bad-tx hover:bg-bad-bg">Видалити</button>
            @else
            <span></span>
            @endif
            <div class="flex gap-2">
                <button wire:click="$set('open', false)" type="button" class="px-4 py-1.5 rounded-lg border border-[#dfe3e0] text-mut hover:bg-[#f5f5f3]">Скасувати</button>
                <button wire:click="save" type="button" class="px-4 py-1.5 rounded-lg bg-acc text-white font-semibold hover:opacity-90">Зберегти</button>
            </div>
        </div>

    </div>
</div>
@endif
</div>
