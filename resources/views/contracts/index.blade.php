@extends('layouts.app')
@section('title', 'Contracts')
@section('content')
@php $tone = ['draft' => 'slate', 'sent' => 'amber', 'active' => 'green', 'declined' => 'red', 'terminated' => 'slate', 'completed' => 'blue']; @endphp
<x-page-title title="Contracts" sub="What each freelancer is hired for and how they are paid.">
    @can('manage-contracts')<a href="{{ route('contracts.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> New contract</a>@endcan
</x-page-title>
<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('contracts.index') }}" class="chip {{ ! $status ? 'chip-active' : '' }}">All</a>
    @foreach (['sent' => 'Awaiting answer', 'active' => 'Active', 'terminated' => 'Ended'] as $k => $l)<a href="{{ route('contracts.index', ['status' => $k]) }}" class="chip {{ $status === $k ? 'chip-active' : '' }}">{{ $l }}</a>@endforeach
</div>
<div class="card divide-y divide-slate-100">
    @forelse ($contracts as $c)
        <a href="{{ route('contracts.show', $c) }}" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50">
            <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-600"><i class="bi bi-file-earmark-ruled"></i></span>
            <div class="min-w-0 flex-1"><div class="truncate font-semibold text-slate-900">{{ $c->title }}</div>
                <div class="truncate text-sm text-slate-500">{{ $c->reference }} · {{ $role->isFreelancer() ? $org->name : $c->freelancer->name }}@if ($c->project) · {{ $c->project->name }}@endif</div></div>
            <span class="text-sm text-slate-600">
                @switch($c->type)
                    @case('hourly') {{ money($c->hourly_rate_minor, $c->currency) }}/hr @break
                    @case('fixed') {{ money($c->fixed_amount_minor, $c->currency) }} fixed @break
                    @case('retainer') {{ money($c->retainer_amount_minor, $c->currency) }}/{{ $c->payment_cycle }} @break
                    @default Milestones · {{ money($c->valueMinor(), $c->currency) }}
                @endswitch
            </span>
            <x-pill :tone="$tone[$c->status] ?? 'slate'">{{ ucfirst($c->status) }}</x-pill>
        </a>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-500">No contracts yet.</p>
    @endforelse
</div>
<div class="mt-6">{{ $contracts->links() }}</div>
@endsection
