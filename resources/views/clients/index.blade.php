@extends('layouts.app')
@section('title', 'My clients')
@section('content')
@php
    $fmt = fn (array $by) => collect($by)->map(fn ($m, $c) => money($m, $c))->implode(' + ');
    $filters = ['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive', 'company' => 'Companies', 'individual' => 'Individuals', 'international' => 'International', 'domestic' => 'Domestic'];
@endphp
<x-page-title title="Clients" sub="Manage your clients, projects and relationships.">
    @can('manage-clients')<a href="{{ route('clients.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> Add client</a>@endcan
</x-page-title>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        @foreach ($filters as $k => $l)
            <a href="{{ route('clients.index', array_filter(['filter' => $k === 'all' ? null : $k, 'q' => $search])) }}" class="chip {{ $filter === $k ? 'chip-active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    <form method="GET" action="{{ route('clients.index') }}" class="flex gap-2" role="search">
        @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
        <input type="search" name="q" value="{{ $search }}" placeholder="Search clients..." class="input w-56" aria-label="Search clients">
        <button class="btn-secondary" aria-label="Search"><i class="bi bi-search"></i></button>
    </form>
</div>

@if ($clients->isEmpty())
    <div class="card px-6 py-12 text-center">
        <i class="bi bi-building text-4xl text-slate-300"></i>
        @if ($search !== '' || $filter !== 'all')
            <p class="mt-3 font-semibold text-slate-700">No clients match.</p>
            <a href="{{ route('clients.index') }}" class="btn-secondary mt-4">Clear filters</a>
        @else
            <p class="mt-3 font-semibold text-slate-700">No clients yet.</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Add your first client and start managing your freelance work.</p>
            @can('manage-clients')<a href="{{ route('clients.create') }}" class="btn-primary mt-4"><i class="bi bi-plus-lg"></i> Add client</a>@endcan
        @endif
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($clients as $c)
            <a href="{{ route('clients.show', $c) }}" class="card flex flex-col p-5 transition hover:border-slate-300">
                <div class="flex items-start gap-3">
                    <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 text-xl text-slate-400">
                        @if ($c->logo_path)<img src="{{ route('assets.client-logo', $c) }}" alt="" class="size-full object-contain">@else<i class="bi {{ $c->type === 'individual' ? 'bi-person' : 'bi-building' }}"></i>@endif</span>
                    <div class="min-w-0 flex-1"><div class="truncate font-semibold text-slate-900">{{ $c->name }}</div>
                        <div class="truncate text-sm text-slate-500">{{ $c->contact_name ?: ucfirst($c->type) }} · {{ \App\Support\Countries::name($c->country_code) ?: 'No country' }}</div></div>
                    <x-pill :tone="$c->status === 'active' ? 'green' : 'slate'">{{ $c->status === 'active' ? 'Active' : 'Inactive' }}</x-pill>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Active projects</dt><dd class="font-semibold text-slate-900">{{ $c->active_projects_count }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Amount due</dt><dd class="truncate font-semibold {{ ($owed[$c->id] ?? []) ? 'text-slate-900' : 'text-slate-400' }}">{{ ($owed[$c->id] ?? []) ? $fmt($owed[$c->id]) : 'Nothing due' }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-slate-500">Last activity</dt><dd class="text-slate-700">{{ ($lastActivity[$c->id] ?? null) ? \Illuminate\Support\Carbon::parse($lastActivity[$c->id])->diffForHumans() : 'No activity yet' }}</dd></div>
                </dl>
            </a>
        @endforeach
    </div>
@endif
@endsection
