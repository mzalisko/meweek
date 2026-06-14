<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DBManager Core</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-canvas text-ink text-[13px]">
    @include('admin.icons')

    <div class="max-w-[1280px] mx-auto my-4 border border-[#e3e5e1] rounded-xl overflow-hidden bg-canvas">

        {{-- Header --}}
        <div class="bg-white border-b border-[#e3e5e1] px-[18px] py-3 flex justify-between items-center">
            <div class="flex gap-3 items-center">
                <b class="text-[14px]">DBManager Core</b>
                {{ $breadcrumb ?? '' }}
            </div>
            <div class="flex gap-4 items-center text-mut">
                <span>@svg('search') пошук ⌘K</span>
                <span class="relative">
                    @svg('bell')<span class="bg-[#a85c52] text-white rounded-lg text-[10px] px-1.5 relative -top-1.5 -left-1">{{ $incidents ?? 0 }}</span>
                </span>
                <span>@svg('user') {{ auth()->user()?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="underline text-mut">вихід</button>
                </form>
            </div>
        </div>

        {{-- Context bar (optional slot) --}}
        {{ $context ?? '' }}

        {{-- Body: sidebar + main --}}
        <div class="flex">

            {{-- Sidebar nav --}}
            <nav class="w-[170px] shrink-0 bg-white border-r border-[#e3e5e1] py-3">
                @php
                    $nav = [
                        ['Дашборд',          'grid',  false],
                        ['Значення',         'tag',   true],
                        ['Сайти і групи',    'sites', false],
                        ['Інциденти',        'alert', false],
                        ['Аудит',            'audit', false],
                        ['Доступи й токени', 'key',   false],
                    ];
                @endphp

                @foreach($nav as [$label, $icon, $active])
                    <div @class([
                        'px-[18px] py-2 flex gap-2.5 items-center',
                        'text-mut' => !$active,
                        'bg-acc-bg border-r-[3px] border-acc text-acc-tx font-bold' => $active,
                    ])>
                        @svg($icon) {{ $label }}
                    </div>
                @endforeach
            </nav>

            {{-- Main content --}}
            <div class="flex-1 min-w-0">
                {{ $slot }}
            </div>

        </div>
    </div>

    @livewireScripts
</body>
</html>
