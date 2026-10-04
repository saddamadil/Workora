@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
@php
    $tone = ['draft' => 'slate', 'sent' => 'amber', 'paid' => 'green', 'overdue' => 'red', 'rejected' => 'orange', 'void' => 'slate', 'partial' => 'blue', 'refunded' => 'slate'];
    $label = ['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue', 'rejected' => 'Sent back', 'void' => 'Cancelled', 'partial' => 'Partially paid', 'refunded' => 'Refunded'];
    $base = $inTeam ? 'team.invoices' : 'invoices.index';
@endphp
<x-page-title title="{{ $inTeam ? 'Team' : 'Invoices' }}" :sub="$inTeam ? 'Your people, how they are paid and what they have invoiced.' : ($role->isFreelancer() ? 'Create, send and track your invoices.' : 'Invoices from your freelancers.')">
    @can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> New invoice</a>@endcan
</x-page-title>
@if ($inTeam) @include('team._tabs', ['active' => 'invoices']) @endif

@include('invoices._stats', ['stats' => $stats])

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        @foreach ([null => 'All', 'draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $k => $l)
            <a href="{{ route($base, array_filter(['status' => $k, 'q' => $search])) }}" class="chip {{ ($status ?: null) === $k ? 'chip-active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    <form method="GET" action="{{ route($base) }}" class="flex gap-2">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        <input type="search" name="q" value="{{ $search }}" placeholder="Search number, client or freelancer" class="input w-64" aria-label="Search invoices">
        <button class="btn-secondary" aria-label="Search"><i class="bi bi-search"></i></button>
    </form>
</div>

<div class="card divide-y divide-slate-100">
    @forelse ($invoices as $i)
        @php $ds = $i->displayStatus(); @endphp
        <a href="{{ route('invoices.show', $i) }}" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50">
            <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-600"><i class="bi bi-receipt"></i></span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-slate-900">{{ $i->number }}</div>
                <div class="truncate text-sm text-slate-500">{{ $i->client?->name ?? $org->name }}@unless ($role->isFreelancer()) · {{ $i->freelancer->name }}@endunless · issued {{ $i->issue_date->format('d M Y') }} · due {{ $i->due_date->format('d M Y') }}</div>
            </div>
            <span class="font-semibold text-slate-900">{{ money($i->total_minor, $i->currency) }}</span>
            <x-pill :tone="$tone[$ds] ?? 'slate'">{{ $label[$ds] ?? ucfirst($ds) }}</x-pill>
        </a>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-500">No invoices here.</p>
    @endforelse
</div>
<div class="mt-6">{{ $invoices->links() }}</div>
@endsection
