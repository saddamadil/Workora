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
    // On pages that work without a company (your profile) there is no org or role.
    $role = $role ?? null;
    $org = $org ?? null;
    $isFreelancer = $role?->isFreelancer() ?? false;
    $has = fn ($r) => \Illuminate\Support\Facades\Route::has($r);
    // [route, icon, label, route-prefix used for the active highlight, visible?]
    $sections = [
        'Work' => [
            ['dashboard', 'bi-grid-1x2', 'Dashboard', 'dashboard', true],
            ['projects.index', 'bi-kanban', $isFreelancer ? 'My projects' : 'Projects', 'projects', true],
            ['tasks.index', 'bi-check2-square', $isFreelancer ? 'My tasks' : 'Tasks', 'tasks', true],
            ['time.index', 'bi-stopwatch', 'Time', 'time', $has('time.index') && Gate::allows('track-time')],
            ['work-requests.index', 'bi-lightbulb', $isFreelancer ? 'My proposals' : 'Proposals', 'work-requests', $has('work-requests.index')],
        ],
        'People' => [
            ['team.index', 'bi-people', 'Team & freelancers', 'team', ! $isFreelancer],
            ['clients.index', 'bi-building', 'Clients', 'clients', ! $isFreelancer],
            ['settings.company', 'bi-gear', 'Company settings', 'settings', ! $isFreelancer && Gate::allows('manage-team')],
        ],
        'Money' => [
            ['contracts.index', 'bi-file-earmark-ruled', 'Contracts', 'contracts', $has('contracts.index') && ($isFreelancer || Gate::allows('see-money'))],
            ['timesheets.index', 'bi-calendar-check', 'Timesheets', 'timesheets', $has('timesheets.index') && Gate::allows('review-time')],
            ['invoices.index', 'bi-receipt', 'Invoices', 'invoices', $has('invoices.index') && ($isFreelancer || Gate::allows('see-money'))],
            ['payments.index', 'bi-cash-coin', $isFreelancer ? 'Earnings' : 'Payments', 'payments', $has('payments.index') && ($isFreelancer || Gate::allows('see-money'))],
        ],
        'Files' => [
            ['files.index', 'bi-folder2-open', 'All files', 'files', true],
            ['shares.index', 'bi-link-45deg', 'Shared links', 'shared', true],
        ],
    ];
    if (! $org) { $sections = []; }
@endphp

{{-- Mobile top bar --}}
<header class="sticky top-0 z-30 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 lg:hidden">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-bold text-slate-900">
        <span class="grid size-8 place-items-center rounded-lg bg-brand-600 text-white"><i class="bi bi-cloud-arrow-up-fill"></i></span> Workora
    </a>
    <button type="button" @click="nav = !nav" class="grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Menu">
        <i class="bi bi-list text-2xl"></i>
    </button>
</header>

<div class="lg:flex">
    <aside :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white p-4 transition-transform lg:sticky lg:top-0 lg:h-screen lg:shrink-0">
        <a href="{{ route('dashboard') }}" class="mb-6 hidden items-center gap-2.5 px-2 text-lg font-bold text-slate-900 lg:flex">
            <span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white"><i class="bi bi-cloud-arrow-up-fill"></i></span> Workora
        </a>

        @if (($myOrgs ?? collect())->count() > 1)
            <div x-data="{ open: false }" class="relative mb-3 pt-12 lg:pt-0">
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 py-2 text-left text-sm font-semibold text-slate-800">
                    <span class="truncate">{{ $org?->name }}</span><i class="bi bi-chevron-expand text-slate-400"></i>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                    @foreach ($myOrgs as $o)
                        <form method="POST" action="{{ route('organizations.switch', $o->slug) }}">@csrf
                            <button class="menu-item {{ $o->id === $org?->id ? 'font-semibold text-brand-700' : '' }}">{{ $o->name }}</button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        <nav class="flex-1 space-y-4 overflow-y-auto pb-3 {{ ($myOrgs ?? collect())->count() > 1 ? '' : 'pt-12 lg:pt-0' }}">
            @foreach ($sections as $title => $items)
                @php $items = array_filter($items, fn ($i) => $i[4]); @endphp
                @if ($items)
                    <div>
                        <div class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $title }}</div>
                        <div class="space-y-0.5">
                            @foreach ($items as [$route, $icon, $label, $prefix])
                                <a href="{{ route($route) }}"
                                   class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium {{ request()->routeIs($prefix.'.*') || request()->routeIs($prefix) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <i class="bi {{ $icon }} text-lg"></i> {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
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
                <a href="{{ route('profile.edit') }}" class="min-w-0 flex-1" title="Your profile">
                    <div class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</div>
                    <div class="truncate text-xs text-slate-500">{{ $role?->label() }} · {{ $org?->name }}</div>
                </a>
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
