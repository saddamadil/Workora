@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
@php $tone = ['draft' => 'slate', 'submitted' => 'amber', 'under_review' => 'amber', 'approved' => 'blue', 'rejected' => 'orange', 'partially_paid' => 'blue', 'paid' => 'green', 'void' => 'slate']; @endphp
<x-page-title title="Invoices" :sub="$role->isFreelancer() ? 'Bill the company for approved work.' : 'Invoices from your freelancers.'">
    @can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> New invoice</a>@endcan
</x-page-title>
<div class="mb-4 flex flex-wrap gap-2">
    @foreach ([null => 'All', 'open' => 'Awaiting approval', 'unpaid' => 'Approved, unpaid', 'paid' => 'Paid', 'draft' => 'Drafts'] as $k => $l)
        @continue(! $role->isFreelancer() && $k === 'draft')
        <a href="{{ route('invoices.index', $k ? ['status' => $k] : []) }}" class="chip {{ ($status ?: null) === $k ? 'chip-active' : '' }}">{{ $l }}</a>
    @endforeach
</div>
<div class="card divide-y divide-slate-100">
    @forelse ($invoices as $i)
        @if (! $role->isFreelancer() && $i->status === 'draft') @continue @endif
        <a href="{{ route('invoices.show', $i) }}" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50">
            <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-600"><i class="bi bi-receipt"></i></span>
            <div class="min-w-0 flex-1"><div class="font-semibold text-slate-900">{{ $i->number }}</div>
                <div class="truncate text-sm text-slate-500">{{ $role->isFreelancer() ? 'Issued '.$i->issue_date->format('d M Y') : $i->freelancer->name }} · due {{ $i->due_date->format('d M Y') }}</div></div>
            <span class="font-semibold text-slate-900">{{ money($i->total_minor, $i->currency) }}</span>
            @if ($i->isOverdue() && in_array($i->status, ['approved', 'partially_paid']))<x-pill tone="red">Overdue</x-pill>@endif
            <x-pill :tone="$tone[$i->status] ?? 'slate'">{{ ucfirst(str_replace('_', ' ', $i->status)) }}</x-pill>
        </a>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-500">No invoices here.</p>
    @endforelse
</div>
<div class="mt-6">{{ $invoices->links() }}</div>
@endsection
