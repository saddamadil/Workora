@extends('layouts.app')
@section('title', 'Clients')
@section('content')
<x-page-title title="Clients" sub="The people and companies you do the work for. Their details fill in invoices automatically.">
    @can('manage-clients')<a href="{{ route('clients.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> Add client</a>@endcan
</x-page-title>
@if ($clients->isEmpty())
    <div class="card px-6 py-12 text-center"><i class="bi bi-building text-4xl text-slate-300"></i><p class="mt-3 font-semibold text-slate-700">No clients yet.</p>
        @can('manage-clients')<a href="{{ route('clients.create') }}" class="btn-primary mt-4">Add your first client</a>@endcan</div>
@else
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($clients as $c)
            <div class="card flex flex-col p-5">
                <div class="flex items-start gap-3">
                    <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 text-xl text-slate-400">
                        @if ($c->logo_path)<img src="{{ route('assets.client-logo', $c) }}" alt="" class="size-full object-contain">@else<i class="bi bi-building"></i>@endif</span>
                    <div class="min-w-0"><div class="truncate font-semibold text-slate-900">{{ $c->name }}</div><div class="truncate text-sm text-slate-500">{{ $c->contact_name ?: 'No contact person' }}</div></div>
                </div>
                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Country</dt><dd class="truncate font-medium text-slate-800">{{ \App\Support\Countries::name($c->country_code) ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Currency</dt><dd class="font-medium text-slate-800">{{ $c->default_currency ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Tax ID</dt><dd class="truncate font-medium text-slate-800">{{ collect($c->tax_ids ?? [])->first() ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Projects</dt><dd class="font-medium text-slate-800">{{ $c->projects_count }}</dd></div>
                </dl>
                <div class="mt-4 flex gap-2">
                    <a href="{{ route('clients.show', $c) }}" class="btn-secondary btn-sm flex-1">View</a>
                    @can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create', ['client' => $c->id]) }}" class="btn-primary btn-sm flex-1"><i class="bi bi-receipt"></i> Create invoice</a>@endcan
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
