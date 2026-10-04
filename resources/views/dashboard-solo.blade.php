@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $first = explode(' ', auth()->user()->name)[0];
    $hour = now()->tz($org->timezone ?? 'UTC')->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $fmt = fn (array $by) => $by ? collect($by)->map(fn ($m, $c) => money($m, $c))->implode(' + ') : money(0, $org->base_currency);
    $prio = ['urgent' => 'red', 'high' => 'orange', 'medium' => 'blue', 'low' => 'slate'];
    $label = [
        'client.created' => 'Client added', 'client.invited' => 'Client invited', 'invoice.sent' => 'Invoice sent', 'invoice.approved' => 'Invoice approved',
        'invoice.rejected' => 'Invoice sent back', 'payment.recorded' => 'Payment recorded', 'invoice.viewed' => 'Client viewed an invoice',
    ];
@endphp
<x-page-title :title="$greeting.', '.$first" sub="Here's what's happening with your freelance business.">
    @can('manage-clients')<a href="{{ route('clients.create') }}" class="btn-secondary"><i class="bi bi-person-plus"></i> Add client</a>@endcan
    @can('create', \App\Models\Project::class)<a href="{{ route('projects.create') }}" class="btn-secondary"><i class="bi bi-kanban"></i> New project</a>@endcan
    @can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create') }}" class="btn-primary"><i class="bi bi-receipt"></i> Create invoice</a>@endcan
</x-page-title>

<div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Active clients" :value="$activeClients" icon="bi-people" tone="slate" :href="route('clients.index', ['filter' => 'active'])" />
    <x-stat label="Active projects" :value="$activeProjects" icon="bi-kanban" tone="slate" :href="route('projects.index', ['status' => 'active'])" />
    <x-stat label="Outstanding" :value="$fmt($outstanding)" icon="bi-hourglass-split" :tone="$outstanding ? 'amber' : 'slate'" :href="route('invoices.index', ['status' => 'sent'])" />
    <x-stat label="This month" :value="$fmt($thisMonth)" icon="bi-cash-coin" tone="green" :href="route('payments.index')" />
</div>

@if ($activeClients === 0)
    <div class="card mb-6 flex flex-wrap items-center gap-4 p-6">
        <span class="grid size-12 place-items-center rounded-xl bg-brand-50 text-2xl text-brand-600"><i class="bi bi-rocket-takeoff"></i></span>
        <div class="min-w-0 flex-1"><h2 class="font-semibold text-slate-900">Start with your first client</h2><p class="text-sm text-slate-500">Add a client, create a project, invite them, then send an invoice when the work is done.</p></div>
        @can('manage-clients')<a href="{{ route('clients.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> Add client</a>@endcan
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="card" aria-labelledby="today">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3"><h2 id="today" class="font-semibold text-slate-900">Today's tasks</h2><a href="{{ route('tasks.index') }}" class="text-sm text-brand-600 hover:underline">All tasks</a></div>
            @forelse ($todayTasks as $t)
                <a href="{{ route('tasks.show', $t) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <div class="min-w-0 flex-1"><div class="truncate font-medium text-slate-900">{{ $t->title }}</div>
                        <div class="truncate text-sm text-slate-500">{{ $t->project?->client?->name ?: 'No client' }} · {{ $t->project?->name }}</div></div>
                    <x-pill :tone="$prio[$t->priority] ?? 'slate'">{{ ucfirst($t->priority) }}</x-pill>
                    <span class="w-20 text-sm {{ $t->due_at->isPast() && ! $t->due_at->isToday() ? 'font-semibold text-red-700' : 'text-slate-600' }}">{{ $t->due_at->isToday() ? 'Today' : ($t->due_at->isPast() ? 'Overdue' : $t->due_at->format('d M')) }}</span>
                    <span class="rounded-full {{ \App\Models\Task::STATUS_STYLES[$t->status] ?? '' }} px-2.5 py-0.5 text-xs font-medium">{{ $t->statusLabel() }}</span>
                </a>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-500"><i class="bi bi-check2-circle text-2xl text-emerald-500"></i><p class="mt-1">Nothing due today. Enjoy the quiet, or plan what is next.</p>
                    <a href="{{ route('tasks.index') }}" class="mt-3 inline-block text-brand-600 hover:underline">Open my tasks</a></div>
            @endforelse
        </section>

        <section class="card" aria-labelledby="recent">
            <div class="border-b border-slate-100 px-5 py-3"><h2 id="recent" class="font-semibold text-slate-900">Recent activity</h2></div>
            @forelse ($activity as $a)
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
                    <span class="text-slate-800">{{ $label[$a->action] ?? ucfirst(str_replace(['.', '_'], ' ', $a->action)) }}@if ($a->user) <span class="text-slate-500">· {{ $a->user->name }}</span>@endif</span>
                    <time class="shrink-0 text-xs text-slate-500" datetime="{{ $a->created_at->toIso8601String() }}">{{ $a->created_at->diffForHumans() }}</time>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500">Activity from your clients and projects shows up here.</p>
            @endforelse
        </section>
    </div>

    <div class="space-y-6">
        <section class="card" aria-labelledby="upcoming">
            <div class="border-b border-slate-100 px-5 py-3"><h2 id="upcoming" class="font-semibold text-slate-900">Upcoming</h2></div>
            @php $any = $upcomingTasks->isNotEmpty() || $upcomingInvoices->isNotEmpty() || $upcomingProjects->isNotEmpty(); @endphp
            @foreach ($upcomingInvoices as $i)
                <a href="{{ route('invoices.show', $i) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm hover:bg-slate-50"><i class="bi bi-receipt text-slate-400"></i><span class="min-w-0 flex-1 truncate">{{ $i->number }} due</span><span class="shrink-0 text-slate-500">{{ $i->due_date->format('d M') }}</span></a>
            @endforeach
            @foreach ($upcomingProjects as $p)
                <a href="{{ route('projects.show', $p) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm hover:bg-slate-50"><i class="bi bi-flag text-slate-400"></i><span class="min-w-0 flex-1 truncate">{{ $p->name }} deadline</span><span class="shrink-0 text-slate-500">{{ $p->deadline->format('d M') }}</span></a>
            @endforeach
            @foreach ($upcomingTasks as $t)
                <a href="{{ route('tasks.show', $t) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50"><i class="bi bi-check2-square text-slate-400"></i><span class="min-w-0 flex-1 truncate">{{ $t->title }}</span><span class="shrink-0 text-slate-500">{{ $t->due_at->format('d M') }}</span></a>
            @endforeach
            @unless ($any)<p class="px-5 py-8 text-center text-sm text-slate-500">No deadlines or invoice due dates coming up.</p>@endunless
        </section>
        <section class="card p-5" aria-labelledby="qa">
            <h2 id="qa" class="mb-3 font-semibold text-slate-900">Quick actions</h2>
            <div class="grid grid-cols-2 gap-2 text-sm">
                @can('manage-clients')<a class="btn-secondary" href="{{ route('clients.create') }}"><i class="bi bi-person-plus"></i> Add client</a>@endcan
                @can('create', \App\Models\Project::class)<a class="btn-secondary" href="{{ route('projects.create') }}"><i class="bi bi-kanban"></i> New project</a>@endcan
                <a class="btn-secondary" href="{{ route('tasks.index') }}"><i class="bi bi-check2-square"></i> Create task</a>
                @can('create', \App\Models\Invoice::class)<a class="btn-secondary" href="{{ route('invoices.create') }}"><i class="bi bi-receipt"></i> Create invoice</a>@endcan
            </div>
        </section>
    </div>
</div>
@endsection
