<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Freelancy') · Freelancy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ nav: false, create: false }"
      @keydown.window="if (!['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName) && !$event.target.isContentEditable && !$event.metaKey && !$event.ctrlKey && !$event.altKey && $event.key === 'n') { $event.preventDefault(); create = true }"
      class="min-h-screen">
@php
    // On pages that work without a company (your profile) there is no org or role.
    $role = $role ?? null;
    $org = $org ?? null;
    $isFreelancer = $role?->isFreelancer() ?? false;
    $isClient = $role?->isClient() ?? false;
    $isSolo = ! $isClient && ($org?->mode === 'solo') && $role?->value === 'owner';
    $has = fn ($r) => \Illuminate\Support\Facades\Route::has($r);
    // [route or null when not built yet, icon, label, active prefix, visible?]
    if ($isClient) {
        $sections = ['' => [
            ['portal.dashboard', 'bi-grid-1x2', 'Dashboard', 'portal.dashboard', true],
            ['portal.projects', 'bi-kanban', 'Our projects', 'portal.projects', true],
            ['portal.tasks', 'bi-check2-square', 'Our tasks', 'portal.tasks', true],
            ['portal.messages.index', 'bi-chat-dots', 'Messages', 'portal.messages', true],
            ['portal.requests.index', 'bi-inbox', 'Requests', 'portal.requests', true],
            ['portal.files.index', 'bi-folder2-open', 'Files', 'portal.files', true],
            ['portal.invoices', 'bi-receipt', 'Invoices', 'portal.invoices', true],
            [null, 'bi-cash-coin', 'Payments', '', true],
            ['portal.profile', 'bi-person-circle', 'Profile', 'portal.profile', true],
            [null, 'bi-building', 'Company', '', true],
            [null, 'bi-life-preserver', 'Help', '', true],
        ]];
    } elseif ($isSolo) {
        $sections = ['' => [
            ['dashboard', 'bi-grid-1x2', 'Dashboard', 'dashboard', true],
            ['clients.index', 'bi-people', 'My clients', 'clients', true],
            ['projects.index', 'bi-kanban', 'My projects', 'projects', true],
            ['tasks.index', 'bi-check2-square', 'My tasks', 'tasks', true],
            ['messages.index', 'bi-chat-dots', 'Messages', 'messages', true],
            ['requests.index', 'bi-inbox', 'Requests', 'requests', true],
            ['files.index', 'bi-folder2-open', 'Files', 'files', true],
            ['time.index', 'bi-stopwatch', 'My work', 'time', $has('time.index')],
            ['invoices.index', 'bi-receipt', 'My invoices', 'invoices', true],
            ['payments.index', 'bi-cash-coin', 'Payments', 'payments', $has('payments.index')],
            [null, 'bi-calendar3', 'Calendar', '', true],
            [null, 'bi-bar-chart', 'Reports', '', true],
            ['profile.edit', 'bi-person-circle', 'Profile', 'profile', true],
            ['settings.company', 'bi-gear', 'Settings', 'settings', Gate::allows('manage-team')],
            [null, 'bi-life-preserver', 'Help', '', true],
        ]];
    } else {
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
    }
    if (! $org) { $sections = []; }

    // The global "+" menu. Only things that exist are links; the rest say so.
    $quick = $isClient ? [] : array_filter([
        ['clients.create', 'bi-person-plus', 'New client', Gate::allows('manage-clients')],
        ['projects.create', 'bi-kanban', 'New project', Gate::allows('create', \App\Models\Project::class)],
        ['tasks.index', 'bi-check2-square', 'New task', true],
        ['invoices.create', 'bi-receipt', 'New invoice', Gate::allows('create', \App\Models\Invoice::class)],
        ['time.index', 'bi-stopwatch', 'Log work', $has('time.index') && Gate::allows('track-time')],
        ['files.index', 'bi-cloud-arrow-up', 'Upload file', true],
    ], fn ($q) => $q[3]);
    $mobile = $isClient
        ? [['portal.dashboard', 'bi-grid-1x2', 'Home', 'portal.dashboard'], ['portal.projects', 'bi-kanban', 'Projects', 'portal.projects'], ['portal.invoices', 'bi-receipt', 'Invoices', 'portal.invoices']]
        : array_values(array_filter([
            ['dashboard', 'bi-grid-1x2', 'Home', 'dashboard'],
            $isFreelancer ? ['projects.index', 'bi-kanban', 'Projects', 'projects'] : ['clients.index', 'bi-people', 'Clients', 'clients'],
            ['invoices.index', 'bi-receipt', 'Invoices', 'invoices'],
        ], fn ($m) => $has($m[0])));
@endphp

{{-- Mobile top bar --}}
<header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden">
    <a href="{{ route($isClient ? 'portal.dashboard' : 'dashboard') }}" class="flex items-center gap-2 font-bold text-slate-900">
        <span class="grid size-8 place-items-center rounded-lg bg-slate-900 text-brand-500"><i class="bi bi-lightning-charge-fill"></i></span> Freelancy
    </a>
    <button type="button" @click="nav = !nav" class="grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Open menu" :aria-expanded="nav">
        <i class="bi bi-list text-2xl"></i>
    </button>
</header>

<div class="lg:flex">
    <aside :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-40 flex w-60 flex-col border-r border-slate-200 bg-white p-3 transition-transform lg:sticky lg:top-0 lg:h-screen lg:shrink-0" aria-label="Main navigation">
        <a href="{{ route($isClient ? 'portal.dashboard' : 'dashboard') }}" class="mb-4 hidden items-center gap-2.5 px-2 pt-1 text-lg font-bold text-slate-900 lg:flex">
            <span class="grid size-9 place-items-center rounded-lg bg-slate-900 text-brand-500"><i class="bi bi-lightning-charge-fill"></i></span> Freelancy
        </a>

        @if ($quick)
            <button type="button" @click="create = true; nav = false" class="btn-primary mb-3 w-full"><i class="bi bi-plus-lg"></i> New <kbd class="ml-1 hidden rounded border border-slate-900/20 px-1.5 text-[11px] font-semibold lg:inline">N</kbd></button>
        @endif

        @if (($myOrgs ?? collect())->count() > 1)
            <div x-data="{ open: false }" class="relative mb-3">
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-sm font-semibold text-slate-800">
                    <span class="truncate">{{ $org?->name }}</span><i class="bi bi-chevron-expand text-slate-400"></i>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                    @foreach ($myOrgs as $o)
                        <form method="POST" action="{{ route('organizations.switch', $o->slug) }}">@csrf
                            <button class="menu-item {{ $o->id === $org?->id ? 'font-semibold text-brand-700' : '' }}">{{ $o->name }}</button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        <nav class="flex-1 space-y-4 overflow-y-auto pb-3 {{ $quick ? '' : 'pt-12 lg:pt-0' }}">
            @foreach ($sections as $title => $items)
                @php $items = array_filter($items, fn ($i) => $i[4]); @endphp
                @if ($items)
                    <div>
                        @if ($title)<div class="mb-1 px-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $title }}</div>@endif
                        <div class="space-y-0.5">
                            @foreach ($items as [$route, $icon, $label, $prefix])
                                @php $active = $route && (request()->routeIs($prefix.'.*') || request()->routeIs($prefix)); @endphp
                                @if ($route)
                                    <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                                       class="flex min-h-11 items-center gap-3 rounded-lg border-l-2 px-3 py-2 text-sm font-medium {{ $active ? 'border-brand-500 bg-slate-100 text-slate-900' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <i class="bi {{ $icon }} text-lg {{ $active ? 'text-brand-600' : '' }}"></i> {{ $label }}
                                    </a>
                                @else
                                    <span class="flex min-h-11 cursor-not-allowed items-center gap-3 rounded-lg border-l-2 border-transparent px-3 py-2 text-sm font-medium text-slate-400" title="Coming soon" aria-disabled="true">
                                        <i class="bi {{ $icon }} text-lg"></i> {{ $label }} <span class="ml-auto rounded bg-slate-100 px-1.5 text-[10px] font-semibold uppercase text-slate-500">Soon</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="mt-auto space-y-3">
            @isset($usedBytes)
                @unless ($isClient)
                <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                    <div class="mb-1 font-semibold text-slate-700"><i class="bi bi-hdd me-1"></i> Storage used</div>
                    {{ \App\Models\File::formatBytes($usedBytes) }} across {{ number_format($totalFiles ?? 0) }} {{ str('file')->plural($totalFiles ?? 0) }}
                </div>
                @endunless
            @endisset

            <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-slate-900 text-sm font-bold text-white">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <a href="{{ $isClient ? route('portal.profile') : route('profile.edit') }}" class="min-w-0 flex-1" title="Your profile">
                    <div class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</div>
                    <div class="truncate text-xs text-slate-500">{{ $isSolo ? 'Freelancer' : $role?->label() }} · {{ $org?->name }}</div>
                </a>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="grid size-10 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" title="Sign out" aria-label="Sign out"><i class="bi bi-box-arrow-right"></i></button>
                </form>
            </div>
        </div>
    </aside>

    <div x-show="nav" x-cloak @click="nav = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>

    <main class="min-w-0 flex-1 px-4 py-6 pb-24 sm:px-8 lg:py-8 lg:pb-8">
        <div class="mx-auto max-w-[1400px]">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
</div>

{{-- Mobile bottom navigation: the three places used most, a central create button, and the full menu. --}}
@if ($org)
<nav class="fixed inset-x-0 bottom-0 z-30 grid border-t border-slate-200 bg-white lg:hidden {{ $quick ? 'grid-cols-5' : 'grid-cols-4' }}" aria-label="Quick navigation" style="padding-bottom: env(safe-area-inset-bottom)">
    @foreach ($mobile as $i => [$route, $icon, $label, $prefix])
        @if ($quick && $i === 2)
            <button type="button" @click="create = true" class="grid min-h-14 place-items-center" aria-label="Create new"><span class="grid size-11 place-items-center rounded-full bg-brand-500 text-xl text-slate-900"><i class="bi bi-plus-lg"></i></span></button>
        @endif
        @php $active = request()->routeIs($prefix.'.*') || request()->routeIs($prefix); @endphp
        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium {{ $active ? 'text-slate-900' : 'text-slate-500' }}">
            <i class="bi {{ $icon }} text-xl {{ $active ? 'text-brand-600' : '' }}"></i>{{ $label }}</a>
    @endforeach
    <button type="button" @click="nav = true" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-slate-500"><i class="bi bi-three-dots text-xl"></i>More</button>
</nav>
@endif

{{-- Quick create: a bottom sheet on phones, a centred dialog on desktop. --}}
@if ($quick)
<div x-show="create" x-cloak @keydown.escape.window="create = false" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Create new">
    <div @click.outside="create = false" class="w-full max-w-md rounded-t-2xl bg-white p-5 sm:rounded-2xl">
        <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-bold text-slate-900">Create new</h2>
            <button type="button" @click="create = false" class="grid size-10 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <div class="grid grid-cols-2 gap-2">
            @foreach ($quick as [$route, $icon, $label])
                <a href="{{ route($route) }}" class="flex min-h-14 items-center gap-3 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-800 hover:bg-slate-50"><i class="bi {{ $icon }} text-lg text-brand-600"></i>{{ $label }}</a>
            @endforeach
            <span class="flex min-h-14 cursor-not-allowed items-center gap-3 rounded-lg border border-dashed border-slate-200 px-3 text-sm text-slate-400" title="Coming soon"><i class="bi bi-chat-dots text-lg"></i>Send message <span class="ml-auto text-[10px] font-semibold uppercase">Soon</span></span>
        </div>
    </div>
</div>
@endif
</body>
</html>
