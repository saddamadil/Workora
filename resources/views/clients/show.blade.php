@extends('layouts.app')
@section('title', $client->name)
@section('content')
@php
    $fmt = fn (array $by) => $by ? collect($by)->map(fn ($m, $c) => money($m, $c))->implode(' + ') : '—';
    $tabs = ['overview' => 'Overview', 'projects' => 'Projects', 'invoices' => 'Invoices'];
    $soon = ['Tasks', 'Messages', 'Files', 'Payments', 'Notes'];
    $money = $invoices->isNotEmpty() || Gate::allows('see-money') || ($role?->value === 'owner');
@endphp
<div class="mb-2 text-sm"><a href="{{ route('clients.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Clients</a></div>

<div class="mb-6 flex flex-wrap items-center gap-4">
    <span class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-2xl border border-slate-200 bg-white text-2xl text-slate-400">
        @if ($client->logo_path)<img src="{{ route('assets.client-logo', $client) }}" alt="" class="size-full object-contain">@else<i class="bi {{ $client->type === 'individual' ? 'bi-person' : 'bi-building' }}"></i>@endif</span>
    <div class="min-w-0 flex-1">
        <h1 class="truncate text-2xl font-bold text-slate-900">{{ $client->name }}</h1>
        <p class="truncate text-sm text-slate-500">{{ collect([\App\Support\Countries::name($client->country_code), $client->email])->filter()->join(' · ') ?: ($client->legal_name ?: 'No details yet') }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @can('create', \App\Models\Project::class)<a href="{{ route('projects.create', ['client' => $client->id]) }}" class="btn-secondary"><i class="bi bi-kanban"></i> New project</a>@endcan
        @can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create', ['client' => $client->id]) }}" class="btn-primary"><i class="bi bi-receipt"></i> Create invoice</a>@endcan
        @can('manage-clients')<a href="{{ route('clients.edit', $client) }}" class="btn-secondary" aria-label="Edit client"><i class="bi bi-pencil"></i> Edit</a>@endcan
    </div>
</div>

<nav class="-mx-1 mb-6 flex gap-1 overflow-x-auto border-b border-slate-200 px-1" aria-label="Client sections">
    @foreach ($tabs as $k => $l)
        <a href="{{ route('clients.show', [$client, 'tab' => $k]) }}" @if ($tab === $k) aria-current="page" @endif
           class="-mb-px shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium {{ $tab === $k ? 'border-brand-500 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $l }}</a>
    @endforeach
    @foreach ($soon as $l)<span class="-mb-px shrink-0 cursor-not-allowed border-b-2 border-transparent px-3 py-2.5 text-sm text-slate-400" title="Coming soon">{{ $l }} <span class="rounded bg-slate-100 px-1.5 text-[10px] uppercase">Soon</span></span>@endforeach
</nav>

@if ($tab === 'overview')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Client since" :value="$client->created_at->format('M Y')" icon="bi-calendar3" tone="slate" />
            <x-stat label="Active projects" :value="$projects->where('status', 'active')->count()" icon="bi-kanban" />
            <x-stat label="Completed" :value="$projects->where('status', 'completed')->count()" icon="bi-check2-circle" tone="green" />
            <x-stat label="Amount due" :value="$fmt($outstanding)" icon="bi-hourglass-split" :tone="$outstanding ? 'amber' : 'slate'" :hint="'Total billed '.$fmt($billed)" />
        </div>
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Current projects</h2><a href="{{ route('clients.show', [$client, 'tab' => 'projects']) }}" class="text-sm text-brand-600 hover:underline">All projects</a></div>
            @forelse ($projects->where('status', 'active')->take(5) as $p)
                @php $pct = $p->tasks_total ? round($p->tasks_done / $p->tasks_total * 100) : 0; @endphp
                <a href="{{ route('projects.show', $p) }}" class="block border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                    <div class="flex justify-between text-sm"><span class="font-medium text-slate-900">{{ $p->name }}</span><span class="text-slate-500">{{ $pct }}%</span></div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ $pct }}%"></div></div>
                </a>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500">No active projects yet. Projects keep this client's work organized.
                    @can('create', \App\Models\Project::class)<div class="mt-3"><a href="{{ route('projects.create', ['client' => $client->id]) }}" class="btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New project</a></div>@endcan</div>
            @endforelse
        </div>
        @if ($client->notes)
            <div class="card p-5"><h2 class="mb-1 flex items-center gap-2 font-semibold text-slate-900">Private notes <x-pill>Only you see this</x-pill></h2><p class="whitespace-pre-line text-sm text-slate-700">{{ $client->notes }}</p></div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-3 font-semibold text-slate-900">Client portal</h2>
            @forelse ($portalUsers as $m)
                <div class="mb-2 flex items-center gap-2 text-sm"><i class="bi bi-check-circle-fill text-emerald-500"></i><span class="min-w-0 truncate">{{ $m->user->name }} <span class="text-slate-500">· {{ $m->user->email }}</span></span></div>
            @empty
                <p class="text-sm text-slate-500">{{ $pendingInvite ? 'Invitation sent to '.$pendingInvite->email.'. Waiting for them to accept.' : 'Invite the client so they can follow projects, share files and see invoices.' }}</p>
            @endforelse
            @can('manage-clients')
                <form method="POST" action="{{ route('clients.invite', $client) }}" class="mt-3">@csrf
                    <button class="btn-secondary w-full" @disabled(! $client->email)><i class="bi bi-envelope"></i> {{ $pendingInvite ? 'Send invitation again' : ($portalUsers->isEmpty() ? 'Invite client' : 'Invite another person') }}</button>
                    @unless ($client->email)<p class="mt-1 text-xs text-slate-500">Add an email first.</p>@endunless
                </form>
            @endcan
        </div>
        <div class="card p-5">
            <dl class="space-y-2 text-sm">
                @foreach ([['Type', ucfirst($client->type)], ['Contact', $client->contact_name], ['Email', $client->email], ['Phone', $client->phone], ['Website', $client->website], ['Currency', $client->default_currency], ['Payment method', $client->payment_method]] as [$l, $v])
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $l }}</dt><dd class="truncate text-right font-medium text-slate-800">{{ $v ?: '—' }}</dd></div>
                @endforeach
                <div class="border-t border-slate-100 pt-2"><dt class="text-slate-500">Billing address</dt>
                    <dd class="mt-1 font-medium text-slate-800">{{ collect([$client->address, $client->city, $client->state, $client->postal_code, \App\Support\Countries::name($client->country_code)])->filter()->join(', ') ?: '—' }}</dd></div>
                @foreach (\App\Support\TaxFields::lines($client->country_code, $client->tax_ids) as [$l, $v])
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $l }}</dt><dd class="font-mono text-slate-800">{{ $v }}</dd></div>
                @endforeach
            </dl>
        </div>
    </div>
</div>
@elseif ($tab === 'projects')
<div class="card divide-y divide-slate-100">
    @forelse ($projects as $p)
        @php $pct = $p->tasks_total ? round($p->tasks_done / $p->tasks_total * 100) : 0; @endphp
        <a href="{{ route('projects.show', $p) }}" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50">
            <div class="min-w-0 flex-1"><div class="font-semibold text-slate-900">{{ $p->name }}</div><div class="text-sm text-slate-500">{{ $p->tasks_done }} of {{ $p->tasks_total }} tasks done @if ($p->deadline)· due {{ $p->deadline->format('d M Y') }}@endif</div></div>
            <div class="w-28"><div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ $pct }}%"></div></div></div>
            <x-pill :tone="$p->status === 'active' ? 'green' : 'slate'">{{ \App\Models\Project::STATUS_LABELS[$p->status] ?? $p->status }}</x-pill>
        </a>
    @empty
        <div class="px-5 py-10 text-center text-sm text-slate-500">Projects keep this client's work organized.
            @can('create', \App\Models\Project::class)<div class="mt-3"><a href="{{ route('projects.create', ['client' => $client->id]) }}" class="btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Create project</a></div>@endcan</div>
    @endforelse
</div>
@else
<div class="card divide-y divide-slate-100">
    @forelse ($invoices as $i)
        <a href="{{ route('invoices.show', $i) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 text-sm hover:bg-slate-50">
            <span class="font-medium text-slate-900">{{ $i->number }} <span class="font-normal text-slate-500">· due {{ $i->due_date->format('d M Y') }}</span></span>
            <span class="text-slate-700">{{ money($i->total_minor, $i->currency) }} · <x-pill>{{ ucfirst($i->displayStatus()) }}</x-pill></span></a>
    @empty
        <div class="px-5 py-10 text-center text-sm text-slate-500">Create your first invoice for this client in a few clicks.
            @can('create', \App\Models\Invoice::class)<div class="mt-3"><a href="{{ route('invoices.create', ['client' => $client->id]) }}" class="btn-primary btn-sm"><i class="bi bi-receipt"></i> Create invoice</a></div>@endcan</div>
    @endforelse
</div>
@endif
@endsection
