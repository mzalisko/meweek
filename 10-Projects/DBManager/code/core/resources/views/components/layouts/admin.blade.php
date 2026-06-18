<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DataBridge Core</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-canvas text-ink text-[13px] h-screen flex flex-col overflow-hidden">
    @include('admin.icons')

    {{-- Header --}}
    <header class="bg-white border-b border-[#e3e5e1] px-5 py-2.5 flex justify-between items-center shrink-0">
        <div class="flex gap-4 items-center min-w-0">
            <span class="flex items-center gap-2.5 shrink-0">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#f4f5f3] border border-[#e3e5e1] text-acc shadow-sm">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="3" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.6"></rect>
                        <rect x="13" y="13" width="8" height="8" rx="1.5" fill="currentColor"></rect>
                        <path d="M11 7h2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
                        <path d="M7 11v2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
                    </svg>
                </span>
                <b class="text-[16px] text-acc-tx whitespace-nowrap font-bold tracking-tight">DataBridge Core</b>
            </span>
            <div class="min-w-0">{{ $breadcrumb ?? '' }}</div>
        </div>
        <div class="flex gap-5 items-center text-mut shrink-0">
            <span class="hidden lg:inline-flex items-center gap-1.5">@svg('search') пошук ⌘K</span>
            <span class="relative inline-flex items-center gap-1">
                @svg('bell')<span class="bg-[#a85c52] text-white rounded-full text-[10px] leading-none px-1.5 py-0.5">{{ $incidents ?? 0 }}</span>
            </span>
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">@svg('user') {{ auth()->user()?->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="underline hover:text-ink">вихід</button>
            </form>
        </div>
    </header>

    {{-- Context bar (optional slot) --}}
    {{ $context ?? '' }}

    {{-- Body: sidebar + main --}}
    <div class="flex flex-1 min-h-0">

        {{-- Sidebar nav --}}
        <nav class="w-[210px] shrink-0 bg-white border-r border-[#e3e5e1] py-3 overflow-y-auto">
            @php
                $canManageAccess = app(\App\Admin\AccessControl::class)->canManageAccess(auth()->user());
                $nav = [
                    ['Дашборд',          'grid',  null],
                    ['Сайт',             'grid',  route('admin.site')],
                    ['Масові операції',  'search', route('admin.values')],
                    ['Сайти і групи',    'sites', route('admin.sites')],
                    ['Інциденти',        'alert', null],
                    ['Аудит',            'audit', route('admin.audit')],
                ];
                if ($canManageAccess) {
                    $nav[] = ['Користувачі', 'user', route('admin.access')];
                }
            @endphp

            @foreach($nav as [$label, $icon, $href])
                @php $active = $href && url()->current() === $href; @endphp

                @if($href)
                    <a href="{{ $href }}" @class([
                        'px-5 py-2.5 flex gap-3 items-center',
                        'text-mut hover:bg-acc-bg/50' => !$active,
                        'bg-acc-bg border-r-[3px] border-acc text-acc-tx font-semibold' => $active,
                    ])>
                        @svg($icon) <span>{{ $label }}</span>
                    </a>
                @else
                    <div class="px-5 py-2.5 flex gap-3 items-center text-mut opacity-60">
                        @svg($icon) <span>{{ $label }}</span>
                    </div>
                @endif
            @endforeach
        </nav>

        {{-- Main content --}}
        <main class="flex-1 min-w-0 overflow-y-auto">
            {{ $slot }}
        </main>

    </div>

    @livewireScripts
</body>
</html>
