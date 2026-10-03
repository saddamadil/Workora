@extends('layouts.app')
@section('title', $mine ? 'My proposals' : 'Proposals')
@section('content')
@php $tone = ['submitted' => 'amber', 'negotiating' => 'amber', 'under_review' => 'amber', 'approved' => 'green', 'rejected' => 'red', 'withdrawn' => 'slate']; @endphp
<x-page-title :title="$mine ? 'My proposals' : 'Proposals'" :sub="$mine ? 'Suggest work you could do and agree a price.' : 'Work your freelancers have offered to do.'">
    @if ($mine)<a href="{{ route('work-requests.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> New proposal</a>@endif
</x-page-title>
<div class="card divide-y divide-slate-100">
    @forelse ($requests as $r)
        <a href="{{ route('work-requests.show', $r) }}" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50">
            <span class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-600"><i class="bi bi-lightbulb"></i></span>
            <div class="min-w-0 flex-1"><div class="truncate font-semibold text-slate-900">{{ $r->title }}</div><div class="truncate text-sm text-slate-500">{{ $r->requestedBy->name }}@if ($r->project) · {{ $r->project->name }}@endif · {{ $r->created_at->diffForHumans() }}</div></div>
            <span class="font-semibold text-slate-900">{{ money($r->currentAmountMinor(), $r->currency) }}</span>
            <x-pill :tone="$tone[$r->status] ?? 'slate'">{{ ucfirst(str_replace('_', ' ', $r->status)) }}</x-pill>
        </a>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-500">{{ $mine ? 'You have not proposed anything yet.' : 'No proposals yet.' }}</p>
    @endforelse
</div>
<div class="mt-6">{{ $requests->links() }}</div>
@endsection
