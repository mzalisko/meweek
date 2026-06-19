<x-slot name="breadcrumb">
    <div class="ml-2 flex items-center gap-3 text-xs">
        <span class="text-mut">/</span>
        <span class="font-semibold text-acc-tx">Масові операції</span>
    </div>
</x-slot>

<x-slot name="context">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-acc-bd bg-acc-bg px-[18px] py-2 text-xs text-acc-tx">
        <div class="flex min-w-0 flex-wrap items-center gap-3">
            @svg('search')
            <span>Цілей: <b>{{ $stats['matched'] }}</b></span>
            <span class="text-mut">·</span>
            <span>Сайтів у зміні: <b>{{ $stats['sites'] }}</b></span>
            <span class="text-mut">·</span>
            <span>Превʼю: <b>{{ $stats['preview'] }}</b></span>
        </div>
        @if($report)
            <div class="shrink-0 rounded-md border border-ok-tx/20 bg-ok-bg px-2 py-1 text-ok-tx">
                Змінено {{ $report['changed'] }} записів на {{ $report['sites'] }} сайтах
            </div>
        @endif
    </div>
</x-slot>

<div class="grid min-h-full grid-cols-1 gap-0 bg-canvas xl:grid-cols-[300px_minmax(520px,1fr)_340px]">
    <aside class="border-b border-[#e3e5e1] bg-white p-3.5 xl:border-b-0 xl:border-r">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h1 class="text-[15px] font-semibold text-acc-tx">Вибірка</h1>
            <button type="button" wire:click="selectFilteredSites"
                class="inline-flex items-center gap-1 rounded-lg border border-[#dfe3e0] px-2 py-1 text-[11px] font-semibold text-mut hover:border-acc hover:text-acc-tx">
                @svg('check', 14) Вибрати видимі
            </button>
        </div>

        <div class="space-y-3">
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Область</label>
                <select wire:model.live="scope" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    <option value="all">Усі доступні сайти</option>
                    <option value="group">Група сайтів</option>
                    <option value="selected">Вибрані сайти</option>
                    <option value="tree">Сайт і дочірні</option>
                </select>
            </div>

            @if($scope === 'group')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Група</label>
                    <select wire:model.live="groupId" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="">Оберіть групу</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($scope === 'tree')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Кореневий сайт</label>
                    <select wire:model.live="rootSiteId" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="">Оберіть сайт</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->domain }} [ID: {{ $site->id }}]</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Пошук сайту</label>
                <input wire:model.live.debounce.300ms="siteSearch" type="search"
                    class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none"
                    placeholder="domain або назва">
            </div>

            <div class="max-h-[32vh] overflow-y-auto rounded-lg border border-[#dfe3e0]">
                @forelse($siteOptions as $site)
                    @php $checked = in_array((int) $site->id, array_map('intval', $selectedSiteIds), true); @endphp
                    <button type="button" wire:click="toggleSite({{ $site->id }})"
                        class="flex w-full items-center gap-2 border-b border-[#edf0ed] px-2.5 py-2 text-left text-[11px] last:border-b-0 hover:bg-[#f6f8f6]">
                        <span @class([
                            'inline-flex h-4 w-4 shrink-0 items-center justify-center rounded border',
                            'border-acc bg-acc text-white' => $checked,
                            'border-[#c8cec9] bg-white' => !$checked,
                        ])>@if($checked) @svg('check', 12) @endif</span>
                        <span class="min-w-0">
                            <span class="block truncate font-mono text-ink">{{ $site->domain }}</span>
                            <span class="block truncate text-mut">{{ $site->group?->name ?? 'Без групи' }} · ID {{ $site->id }}</span>
                        </span>
                    </button>
                @empty
                    <div class="px-3 py-4 text-center text-xs text-mut">Немає доступних сайтів.</div>
                @endforelse
            </div>

            <button type="button" wire:click="clearSiteSelection"
                class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-mut hover:text-acc-tx">
                @svg('x', 14) Очистити вибірку
            </button>
        </div>
    </aside>

    <main class="min-w-0 p-3.5">
        <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-5">
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Тип</label>
                <select wire:model.live="targetType" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    <option value="all">Усі типи</option>
                    <option value="phone">Телефони</option>
                    <option value="messenger">Месенджери</option>
                    <option value="price">Ціни</option>
                    <option value="text">Текст</option>
                    <option value="address">Адреси</option>
                    <option value="social">Соцмережі</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Стан</label>
                <select wire:model.live="stateFilter" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    <option value="">Будь-який</option>
                    <option value="active">Активний</option>
                    <option value="hidden">Прихований</option>
                    <option value="down">Є впалий номер</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Гео</label>
                <select wire:model.live="geoFilter" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    <option value="">Будь-яке</option>
                    <option value="WORLD">WORLD</option>
                    @foreach($geoTags as $tag)
                        <option value="{{ $tag->code }}">{{ $tag->code }} · {{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Пошук</label>
                <input wire:model.live.debounce.300ms="search" type="search"
                    class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none"
                    placeholder="key, value, url">
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Номер</label>
                <input wire:model.live.debounce.300ms="phoneFilter" type="search"
                    class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none"
                    placeholder="+380 або фрагмент">
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-[#dfe3e0] bg-white">
            <div class="min-w-[980px]">
                <div class="grid grid-cols-[minmax(150px,1.2fr)_minmax(110px,.8fr)_96px_110px_minmax(160px,1fr)_minmax(190px,1.4fr)] gap-2 border-b border-[#dfe3e0] bg-[#f6f8f6] px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-mut">
                    <div>Сайт</div>
                    <div>Група</div>
                    <div>Тип</div>
                    <div>Гео</div>
                    <div>Ключ</div>
                    <div>Поточне значення</div>
                </div>

                <div class="max-h-[calc(100vh-245px)] overflow-y-auto">
                    @forelse($previewRows as $row)
                        <div class="grid grid-cols-[minmax(150px,1.2fr)_minmax(110px,.8fr)_96px_110px_minmax(160px,1fr)_minmax(190px,1.4fr)] gap-2 border-b border-[#edf0ed] px-3 py-2 text-[12px] last:border-b-0 hover:bg-[#fafbfa]">
                            <div class="min-w-0 font-mono text-ink">
                                <span class="block truncate">{{ $row['site'] }}</span>
                                <span class="text-[10px] text-mut">{{ $row['kind'] === 'phone' ? 'номер' : 'значення' }}</span>
                            </div>
                            <div class="min-w-0 truncate text-mut">{{ $row['group'] }}</div>
                            <div>
                                <span class="inline-flex rounded-md bg-[#eef1ee] px-1.5 py-0.5 text-[10px] font-semibold text-acc-tx">{{ $row['type'] }}</span>
                                <span class="mt-1 block text-[10px] text-mut">{{ $row['state'] }}</span>
                            </div>
                            <div class="flex min-w-0 flex-wrap gap-1">
                                @foreach($row['geo'] as $geo)
                                    <span @class([
                                        'rounded-md px-1.5 py-0.5 text-[10px] font-semibold leading-none',
                                        'bg-bad-bg text-bad-tx' => str_starts_with($geo, '!'),
                                        'bg-[#eef1ee] text-mut' => !str_starts_with($geo, '!'),
                                    ])>{{ $geo }}</span>
                                @endforeach
                            </div>
                            <div class="min-w-0 truncate font-mono text-[#3c5a42]">{{ $row['key'] }}</div>
                            <div class="min-w-0 truncate text-ink" title="{{ $row['value'] }}">{{ $row['value'] }}</div>
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <div class="text-sm font-semibold text-ink">Немає цілей для зміни</div>
                            <div class="mt-1 text-xs text-mut">Змініть область, тип, geo, стан або пошук.</div>
                        </div>
                @endforelse
                </div>
            </div>
        </div>
        @if($stats['limited'])
            <div class="mt-2 rounded-lg border border-[#dfe3e0] bg-white px-3 py-2 text-[11px] text-mut">
                Показано перші {{ $stats['preview'] }} цілей із {{ $stats['matched'] }}. Операція застосовується до всієї поточної вибірки.
            </div>
        @endif
    </main>

    <aside class="border-t border-[#e3e5e1] bg-white p-3.5 xl:border-l xl:border-t-0">
        <h2 class="mb-3 text-[15px] font-semibold text-acc-tx">Операція</h2>

        <div class="space-y-3">
            @php
                $replacePhoneWithoutFilter = $operation === 'replace_phone' && trim($phoneFilter) === '';
                $applyDisabled = $stats['matched'] === 0 || $replacePhoneWithoutFilter;
            @endphp

            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-lg border border-[#dfe3e0] bg-[#f6f8f6] px-2.5 py-2">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-mut">Цілі</div>
                    <div class="mt-1 text-[18px] font-semibold text-ink">{{ $stats['matched'] }}</div>
                </div>
                <div class="rounded-lg border border-[#dfe3e0] bg-[#f6f8f6] px-2.5 py-2">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-mut">Сайти</div>
                    <div class="mt-1 text-[18px] font-semibold text-ink">{{ $stats['sites'] }}</div>
                </div>
                <div class="rounded-lg border border-[#dfe3e0] bg-[#f6f8f6] px-2.5 py-2">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-mut">Номери</div>
                    <div class="mt-1 text-[18px] font-semibold text-ink">{{ $stats['phones'] }}</div>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Дія</label>
                <select wire:model.live="operation" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    <option value="replace_text">Знайти й замінити в key/content</option>
                    <option value="set_value">Встановити content.value</option>
                    <option value="set_geo">Змінити geo-теги</option>
                    <option value="set_status">Показати / приховати слот</option>
                    <option value="replace_phone">Замінити номер</option>
                    <option value="set_phone_status">Стан номера active/down</option>
                </select>
                @error('operation') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
            </div>

            @if($operation === 'replace_text')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Знайти</label>
                    <input wire:model.live="findText" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                    @error('findText') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Замінити на</label>
                    <input wire:model.live="replaceText" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                </div>
            @elseif($operation === 'set_value')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Нове значення</label>
                    <textarea wire:model.live="contentValue" rows="4" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none"></textarea>
                </div>
            @elseif($operation === 'set_status')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Новий стан слота</label>
                    <select wire:model.live="statusValue" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="active">active</option>
                        <option value="hidden">hidden</option>
                    </select>
                </div>
            @elseif($operation === 'set_geo')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Режим geo</label>
                    <select wire:model.live="geoMode" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="replace">Замінити набір</option>
                        <option value="add">Додати до наявних</option>
                        <option value="remove">Прибрати з наявних</option>
                    </select>
                </div>
                <div class="max-h-[30vh] overflow-y-auto rounded-lg border border-[#dfe3e0] p-2">
                    @foreach($geoTags as $tag)
                        <label class="flex items-center gap-2 rounded-md px-1.5 py-1 text-[12px] hover:bg-[#f6f8f6]">
                            <input wire:model.live="geoCodes" type="checkbox" value="{{ $tag->code }}" class="rounded border-[#c8cec9] text-acc focus:ring-acc">
                            <span class="font-mono">{{ $tag->code }}</span>
                            <span class="min-w-0 truncate text-mut">{{ $tag->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('geoCodes') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
            @elseif($operation === 'replace_phone')
                <div @class([
                    'rounded-lg border px-3 py-2 text-[12px]',
                    'border-bad-tx/20 bg-bad-bg text-bad-tx' => $replacePhoneWithoutFilter,
                    'border-[#dfe3e0] bg-[#f6f8f6] text-mut' => !$replacePhoneWithoutFilter,
                ])>
                    Фільтр “Номер” вище визначає, які записи замінювати.
                </div>
                @error('phoneFilter') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Новий номер</label>
                    <input wire:model.live="phoneReplacement" type="text" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none" placeholder="+380...">
                    @error('phoneReplacement') <div class="mt-1 text-[11px] text-bad-tx">{{ $message }}</div> @enderror
                </div>
            @elseif($operation === 'set_phone_status')
                <div>
                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-mut">Стан номера</label>
                    <select wire:model.live="phoneStatus" class="w-full rounded-lg border border-[#dfe3e0] px-2.5 py-2 text-xs focus:border-acc focus:outline-none">
                        <option value="active">active</option>
                        <option value="down">down</option>
                    </select>
                </div>
            @endif

            <label class="flex items-center gap-2 rounded-lg border border-[#dfe3e0] px-3 py-2 text-[12px] text-ink">
                <input wire:model.live="publishAfterApply" type="checkbox" class="rounded border-[#c8cec9] text-acc focus:ring-acc">
                <span>Публікувати сайти після зміни</span>
            </label>

            <button type="button" wire:click="apply"
                wire:confirm="Застосувати масову операцію до поточної вибірки?"
                @disabled($applyDisabled)
                @class([
                    'inline-flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2.5 text-xs font-bold',
                    'bg-acc text-white hover:bg-acc/90' => !$applyDisabled,
                    'cursor-not-allowed bg-[#dfe3e0] text-mut' => $applyDisabled,
                ])>
                @svg('check') Застосувати до {{ $stats['matched'] }} цілей
            </button>

            @if($report)
                <div class="rounded-lg border border-ok-tx/20 bg-ok-bg px-3 py-2 text-[12px] text-ok-tx">
                    Batch: <span class="font-mono">{{ $report['batch'] }}</span>
                </div>
            @endif
        </div>
    </aside>
</div>
