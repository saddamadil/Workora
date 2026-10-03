<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Files') · Workora</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ nav: false }" class="min-h-screen">
@php
    $org = app(\App\Support\Tenancy::class)->organization();
    $links = [
        ['files.index', 'bi-folder2-open', 'My files'],
        ['shares.index', 'bi-link-45deg', 'Shared links'],
        ['drive.index', 'bi-google', 'Google Drive'],
    ];
@endphp

{{-- Mobile top bar --}}
<header class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
    <a href="{{ route('files.index') }}" class="flex items-center gap-2 font-bold text-slate-900">
        <span class="grid size-8 place-items-center rounded-lg bg-brand-600 text-white"><i class="bi bi-cloud-arrow-up-fill"></i></span> Workora
    </a>
    <button type="button" @click="nav = !nav" class="grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Menu">
        <i class="bi bi-list text-2xl"></i>
    </button>
</header>

<div class="lg:flex">
    <aside :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white p-4 transition-transform lg:sticky lg:top-0 lg:h-screen lg:shrink-0">
        <a href="{{ route('files.index') }}" class="mb-6 hidden items-center gap-2.5 px-2 text-lg font-bold text-slate-900 lg:flex">
            <span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white"><i class="bi bi-cloud-arrow-up-fill"></i></span> Workora
        </a>

        <nav class="space-y-1 pt-12 lg:pt-0">
            @foreach ($links as [$route, $icon, $label])
                <a href="{{ route($route) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium {{ request()->routeIs(explode('.', $route)[0].'.*') ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50' }}">
                    <i class="bi {{ $icon }} text-lg"></i> {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="mt-auto space-y-3">
            @isset($usedBytes)
                <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500">
                    <div class="mb-1 font-semibold text-slate-700"><i class="bi bi-hdd me-1"></i> Storage used</div>
                    {{ \App\Models\File::formatBytes($usedBytes) }} across {{ number_format($totalFiles ?? 0) }} {{ str('file')->plural($totalFiles ?? 0) }}
                </div>
            @endisset

            <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</div>
                    <div class="truncate text-xs text-slate-500">{{ $org?->name }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="grid size-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" title="Sign out" aria-label="Sign out"><i class="bi bi-box-arrow-right"></i></button>
                </form>
            </div>
        </div>
    </aside>

    <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>

    <main class="min-w-0 flex-1 px-4 py-6 sm:px-8 lg:py-8">
        <div class="mx-auto max-w-6xl">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
