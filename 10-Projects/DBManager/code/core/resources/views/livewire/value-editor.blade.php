<div>
@if($open)
<div class="fixed inset-0 z-40 flex items-center justify-center bg-black/30">
    <div class="w-full max-w-md bg-white border border-[#e3e5e1] rounded-xl shadow-lg p-6 text-[13px]">

        {{-- Header --}}
        <div class="flex justify-between items-center mb-4">
            <b class="text-ink text-[15px]">{{ $valueId ? 'Редагувати значення' : 'Додати значення' }}</b>
            <button wire:click="$set('open', false)" class="text-mut hover:text-ink" aria-label="Закрити">✕</button>
        </div>

        {{-- Type --}}
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Тип</label>
            <select wire:model.live="type" class="w-full border border-[#e3e5e1] rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-[#5f7d6e]">
                <option value="text">Текст</option>
                <option value="price">Ціна</option>
                <option value="messenger">Месенджер</option>
                <option value="address">Адреса</option>
                <option value="social">Соцмережа</option>
                <option value="phone">Телефон</option>
            </select>
            @error('type')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
        </div>

        {{-- Key --}}
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Ключ</label>
            <input wire:model="key" type="text" placeholder="price_basic" class="w-full border border-[#e3e5e1] rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5f7d6e] @error('key') border-red-400 @enderror" />
            @error('key')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
        </div>

        {{-- Value --}}
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Значення</label>
            <input wire:model="value" type="text" class="w-full border border-[#e3e5e1] rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5f7d6e]" />
            @error('value')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
        </div>

        {{-- Messenger-specific fields --}}
        @if($type === 'messenger')
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">Мережа</label>
            <input wire:model="network" type="text" placeholder="telegram" class="w-full border border-[#e3e5e1] rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5f7d6e]" />
        </div>
        <div class="mb-3">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1">URL</label>
            <input wire:model="url" type="text" placeholder="https://t.me/example" class="w-full border border-[#e3e5e1] rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5f7d6e]" />
        </div>
        @endif

        {{-- Scope --}}
        <div class="mb-4">
            <label class="block text-mut uppercase tracking-[.06em] text-[11px] mb-1.5">Область дії</label>
            <div class="flex gap-4">
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input wire:model="scope" type="radio" value="site" class="accent-[#5f7d6e]" />
                    <span>● Цей сайт</span>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input wire:model="scope" type="radio" value="group" class="accent-[#5f7d6e]" />
                    <span>○ Уся група</span>
                </label>
            </div>
            @error('scope')<p class="text-red-500 text-[11px] mt-0.5">{{ $message }}</p>@enderror
        </div>

        {{-- Buttons --}}
        <div class="flex justify-between gap-2">
            @if($valueId)
            <button wire:click="delete" wire:confirm="Видалити це значення?" type="button" class="px-4 py-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50">Видалити</button>
            @else
            <span></span>
            @endif
            <div class="flex gap-2">
                <button wire:click="$set('open', false)" type="button" class="px-4 py-1.5 rounded-lg border border-[#e3e5e1] text-ink hover:bg-[#f5f5f3]">Скасувати</button>
                <button wire:click="save" type="button" class="px-4 py-1.5 rounded-lg bg-[#5f7d6e] text-white hover:bg-[#4e6b5e]">Зберегти</button>
            </div>
        </div>

    </div>
</div>
@endif
</div>
