@extends('layouts.app')
@section('title', 'Timesheets')
@section('content')
@php $tone = ['draft' => 'slate', 'submitted' => 'amber', 'approved' => 'green', 'rejected' => 'orange']; @endphp
<x-page-title title="Timesheets" sub="Approve the hours people worked." />
<div class="mb-4 flex flex-wrap gap-2">
    @foreach (['submitted' => 'Waiting', 'approved' => 'Approved', 'rejected' => 'Sent back', 'all' => 'All'] as $k => $l)<a href="{{ route('timesheets.index', ['status' => $k]) }}" class="chip {{ $status === $k ? 'chip-active' : '' }}">{{ $l }}</a>@endforeach
</div>
<div class="card divide-y divide-slate-100">
    @forelse ($timesheets as $t)
        <a href="{{ route('timesheets.show', $t) }}" class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50">
            <span class="grid size-10 place-items-center rounded-full bg-brand-100 font-bold text-brand-700">{{ strtoupper(substr($t->freelancer->name, 0, 1)) }}</span>
            <div class="min-w-0 flex-1"><div class="font-semibold text-slate-900">{{ $t->freelancer->name }}</div><div class="text-sm text-slate-500">{{ $t->period_start->format('d M') }} – {{ $t->period_end->format('d M Y') }}</div></div>
            <span class="font-medium text-slate-900" title="Billable hours">{{ hours($t->total_minutes) }}</span>
            @can('see-money')<span class="hidden text-sm text-slate-500 sm:inline">{{ money($t->total_amount_minor, $t->currency) }}</span>@endcan
            <x-pill :tone="$tone[$t->status] ?? 'slate'">{{ ucfirst($t->status) }}</x-pill>
        </a>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-500">{{ $status === 'submitted' ? 'Nothing is waiting for approval.' : 'No timesheets here.' }}</p>
    @endforelse
</div>
<div class="mt-6">{{ $timesheets->links() }}</div>
@endsection
