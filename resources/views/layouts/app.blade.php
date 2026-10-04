<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
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
<body x-data="{ nav: false, create: false, search: false }"
      @keydown.window.prevent.ctrl.k="search = true; $nextTick(() => $refs.q && $refs.q.focus())"
      @keydown.window.prevent.meta.k="search = true; $nextTick(() => $refs.q && $refs.q.focus())"
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
            ['portal.projects', 'bi-kanban', __('ui.our_projects'), 'portal.projects', true],
            ['portal.tasks', 'bi-check2-square', __('ui.our_tasks'), 'portal.tasks', true],
            ['portal.messages.index', 'bi-chat-dots', __('ui.messages'), 'portal.messages', true],
            ['portal.requests.index', 'bi-inbox', __('ui.requests'), 'portal.requests', true],
            ['portal.files.index', 'bi-folder2-open', __('ui.files'), 'portal.files', true],
            ['portal.calendar.index', 'bi-calendar3', __('ui.calendar'), 'portal.calendar', true],
            ['portal.invoices', 'bi-receipt', __('ui.invoices'), 'portal.invoices', true],
            ['portal.quotes', 'bi-file-earmark-text', 'Quotes', 'portal.quotes', true],
            ['portal.agreements', 'bi-pen', 'Agreements', 'portal.agreements', true],
            ['portal.hours', 'bi-clock-history', 'Hours', 'portal.hours', true],
            ['portal.payments', 'bi-cash-coin', __('ui.payments'), 'portal.payments', true],
            ['portal.profile', 'bi-person-circle', __('ui.profile'), 'portal.profile', true],
            ['portal.company', 'bi-building', __('ui.company'), 'portal.company', true],
            ['portal.team', 'bi-people-fill', 'Our team', 'portal.team', $role?->isClientOwner() ?? false],
            ['settings.index', 'bi-gear', __('ui.settings'), 'settings.index', true],
            ['help', 'bi-life-preserver', __('ui.help'), 'help', true],
        ]];
    } elseif ($isSolo) {
        $sections = ['' => [
            ['dashboard', 'bi-grid-1x2', __('ui.dashboard'), 'dashboard', true],
            ['clients.index', 'bi-people', __('ui.my_clients'), 'clients', true],
            ['projects.index', 'bi-kanban', __('ui.my_projects'), 'projects', true],
            ['tasks.index', 'bi-check2-square', __('ui.my_tasks'), 'tasks', true],
            ['messages.index', 'bi-chat-dots', __('ui.messages'), 'messages', true],
            ['requests.index', 'bi-inbox', __('ui.requests'), 'requests', true],
            ['files.index', 'bi-folder2-open', __('ui.files'), 'files', true],
            ['time.index', 'bi-stopwatch', __('ui.my_work'), 'time', $has('time.index')],
            ['quotes.index', 'bi-file-earmark-text', 'Quotes', 'quotes', true],
            ['agreements.index', 'bi-pen', 'Agreements', 'agreements', true],
            ['invoices.index', 'bi-receipt', __('ui.my_invoices'), 'invoices', true],
            ['expenses.index', 'bi-wallet2', 'Expenses', 'expenses', true],
            ['payments.index', 'bi-cash-coin', __('ui.payments'), 'payments', $has('payments.index')],
            ['calendar.index', 'bi-calendar3', __('ui.calendar'), 'calendar', true],
            ['bookings.index', 'bi-calendar-plus', 'Bookings', 'bookings', true],
            ['reports.index', 'bi-bar-chart', __('ui.reports'), 'reports', true],
            ['profile.edit', 'bi-person-circle', __('ui.profile'), 'profile', true],
            ['team.payment-profiles', 'bi-bank', __('ui.payment_profiles'), 'team', true],
            ['settings.index', 'bi-gear', __('ui.settings'), 'settings', true],
            ['help', 'bi-life-preserver', __('ui.help'), 'help', true],
        ]];
    } else {
        $sections = [
            'Work' => [
                ['dashboard', 'bi-grid-1x2', __('ui.dashboard'), 'dashboard', true],
                ['projects.index', 'bi-kanban', $isFreelancer ? __('ui.my_projects') : 'Projects', 'projects', true],
                ['tasks.index', 'bi-check2-square', $isFreelancer ? __('ui.my_tasks') : 'Tasks', 'tasks', true],
                ['bookings.index', 'bi-calendar-plus', 'Bookings', 'bookings', true],
                ['time.index', 'bi-stopwatch', 'Time', 'time', $has('time.index') && Gate::allows('track-time')],
                ['work-requests.index', 'bi-lightbulb', $isFreelancer ? 'My proposals' : 'Proposals', 'work-requests', $has('work-requests.index')],
            ],
            'People' => [
                ['team.index', 'bi-people', 'Team & freelancers', 'team', ! $isFreelancer],
                ['clients.index', 'bi-building', 'Clients', 'clients', ! $isFreelancer],
                ['settings.company', 'bi-gear', 'Company settings', 'settings', ! $isFreelancer && Gate::allows('manage-team')],
            ],
            'Money' => [
                ['quotes.index', 'bi-file-earmark-text', 'Quotes', 'quotes', $isFreelancer || Gate::allows('see-money')],
                ['agreements.index', 'bi-pen', 'Agreements', 'agreements', $isFreelancer || Gate::allows('see-money')],
                ['expenses.index', 'bi-wallet2', 'Expenses', 'expenses', $isFreelancer || Gate::allows('see-money')],
                ['contracts.index', 'bi-file-earmark-ruled', 'Contracts', 'contracts', $has('contracts.index') && ($isFreelancer || Gate::allows('see-money'))],
                ['timesheets.index', 'bi-calendar-check', 'Timesheets', 'timesheets', $has('timesheets.index') && Gate::allows('review-time')],
                ['invoices.index', 'bi-receipt', 'Invoices', 'invoices', $has('invoices.index') && ($isFreelancer || Gate::allows('see-money'))],
                ['payments.index', 'bi-cash-coin', $isFreelancer ? 'Earnings' : __('ui.payments'), 'payments', $has('payments.index') && ($isFreelancer || Gate::allows('see-money'))],
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
        ? [['portal.dashboard', 'bi-grid-1x2', __('ui.home'), 'portal.dashboard'], ['portal.projects', 'bi-kanban', __('ui.projects'), 'portal.projects'], ['portal.invoices', 'bi-receipt', __('ui.invoices'), 'portal.invoices']]
        : array_values(array_filter([
            ['dashboard', 'bi-grid-1x2', __('ui.home'), 'dashboard'],
            $isFreelancer ? ['projects.index', 'bi-kanban', __('ui.projects'), 'projects'] : ['clients.index', 'bi-people', __('ui.clients'), 'clients'],
            ['invoices.index', 'bi-receipt', __('ui.invoices'), 'invoices'],
        ], fn ($m) => $has($m[0])));
@endphp

{{-- Mobile top bar --}}
<header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden">
    <a href="{{ route($isClient ? 'portal.dashboard' : 'dashboard') }}" class="flex items-center gap-2 font-bold text-slate-900">
        <span class="grid size-8 place-items-center rounded-lg bg-slate-900 text-brand-500"><i class="bi bi-lightning-charge-fill"></i></span> Freelancy
    </a>
    <button type="button" @click="search = true; $nextTick(() => $refs.q && $refs.q.focus())" class="ml-auto grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Search"><i class="bi bi-search text-xl"></i></button>
    <a href="{{ route('notifications.index') }}" class="relative mr-1 grid size-11 place-items-center rounded-lg text-slate-600 hover:bg-slate-100" aria-label="Notifications"><i class="bi bi-bell text-xl"></i>@if (($unreadNotifications ?? 0) > 0)<span class="absolute right-1.5 top-1.5 size-2.5 rounded-full bg-brand-500"></span>@endif</a>
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

        <button type="button" @click="search = true; nav = false; $nextTick(() => $refs.q && $refs.q.focus())" class="mb-2 flex min-h-11 w-full items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-500 hover:bg-slate-50">
            <i class="bi bi-search text-lg"></i> {{ __('ui.search') }} <kbd class="ml-auto hidden rounded border border-slate-200 px-1.5 text-[11px] lg:inline">Ctrl K</kbd>
        </button>
        <a href="{{ route('notifications.index') }}" class="mb-2 flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 {{ request()->routeIs('notifications.*') ? 'bg-slate-100 text-slate-900' : '' }}">
            <i class="bi bi-bell text-lg"></i> {{ __('ui.notifications') }} @if (($unreadNotifications ?? 0) > 0)<span class="ml-auto rounded-full bg-brand-500 px-2 text-xs font-semibold text-slate-900">{{ $unreadNotifications }}<span class="sr-only"> unread</span></span>@endif
        </a>

        @if ($quick)
            <button type="button" @click="create = true; nav = false" class="btn-primary mb-3 w-full"><i class="bi bi-plus-lg"></i> {{ __('ui.new') }} <kbd class="ml-1 hidden rounded border border-slate-900/20 px-1.5 text-[11px] font-semibold lg:inline">N</kbd></button>
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
            @if (empty(auth()->user()->getAttributes()['email_verified_at'] ?? null))<div class="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><span>Please verify your email address.</span><form method="POST" action="{{ route('verification.send') }}">@csrf<button class="font-semibold underline">Send me the link</button></form></div>@endif
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
    <button type="button" @click="nav = true" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-slate-500"><i class="bi bi-three-dots text-xl"></i>{{ __('ui.more') }}</button>
</nav>
@endif

{{-- Search: Ctrl or Cmd + K. Results are grouped by kind and limited to what this person may see. --}}
@if ($org)
<div x-show="search" x-cloak @keydown.escape.window="search = false" class="fixed inset-0 z-[60] flex items-start justify-center bg-slate-900/40 p-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="Search"
     x-data="{ term: '', groups: [], busy: false, t: null, run() { clearTimeout(this.t); if (this.term.trim().length < 2) { this.groups = []; return } this.t = setTimeout(async () => { this.busy = true; const r = await fetch(@js(route('search.index')) + '?q=' + encodeURIComponent(this.term), { headers: { Accept: 'application/json' } }); this.groups = r.ok ? (await r.json()).groups : []; this.busy = false }, 200) } }">
    <div @click.outside="search = false" class="w-full max-w-xl overflow-hidden rounded-xl bg-white shadow-xl">
        <div class="flex items-center gap-3 border-b border-slate-200 px-4"><i class="bi bi-search text-slate-400"></i>
            <input x-ref="q" x-model="term" @input="run()" type="search" placeholder="Search clients, projects, tasks, messages, invoices, files" class="min-h-14 w-full border-0 bg-transparent text-sm focus:outline-none focus:ring-0" aria-label="Search"></div>
        <div class="max-h-[60vh] overflow-y-auto">
            <template x-for="g in groups" :key="g.title"><div><div class="bg-slate-50 px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500" x-text="g.title"></div>
                <template x-for="i in g.items" :key="i.url"><a :href="i.url" class="block px-4 py-2.5 hover:bg-slate-50"><div class="truncate text-sm font-medium text-slate-900" x-text="i.title"></div><div class="truncate text-xs text-slate-500" x-text="i.sub"></div></a></template></div></template>
            <p x-show="term.trim().length >= 2 && !busy && groups.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">Nothing found for that.</p>
            <p x-show="term.trim().length < 2" class="px-4 py-8 text-center text-sm text-slate-500">Type at least two letters.</p>
        </div>
    </div>
</div>
@endif

{{-- Quick create: a bottom sheet on phones, a centred dialog on desktop. --}}
@if ($quick)
<div x-show="create" x-cloak @keydown.escape.window="create = false" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Create new">
    <div @click.outside="create = false" class="w-full max-w-md rounded-t-2xl bg-white p-5 sm:rounded-2xl">
        <div class="mb-3 flex items-center justify-between"><h2 class="text-lg font-bold text-slate-900">{{ __('ui.create_new') }}</h2>
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
