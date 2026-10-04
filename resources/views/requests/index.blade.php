@extends('layouts.app')
@section('title', 'Requests')
@section('content')
@php $tone = ['new' => 'blue', 'discussing' => 'amber', 'accepted' => 'green', 'in_progress' => 'indigo', 'completed' => 'green', 'declined' => 'slate']; @endphp
<x-page-title title="Requests" sub="Work your clients have asked for." />
<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('requests.index') }}" class="chip {{ $status === '' ? 'chip-active' : '' }}">All</a>
    @foreach (\App\Models\ClientRequest::STATUSES as $k => $l)<a href="{{ route('requests.index', ['status' => $k]) }}" class="chip {{ $status === $k ? 'chip-active' : '' }}">{{ $l }} @if (($counts[$k] ?? 0) > 0)<span class="text-xs opacity-70">{{ $counts[$k] }}</span>@endif</a>@endforeach
</div>
<div class="card divide-y divide-slate-100">
    @forelse ($requests as $r)
        <a href="{{ route('requests.show', $r) }}" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50"><div class="min-w-0 flex-1"><div class="font-semibold text-slate-900">{{ $r->title }}</div><div class="text-sm text-slate-500">{{ $r->client->name }}@if ($r->project) · {{ $r->project->name }}@endif · {{ $r->created_at->diffForHumans() }}</div></div><x-pill :tone="$tone[$r->status] ?? 'slate'">{{ \App\Models\ClientRequest::STATUSES[$r->status] }}</x-pill></a>
    @empty
        <p class="px-5 py-12 text-center text-sm text-slate-500">No requests{{ $status ? ' with this status' : ' yet' }}. When a client asks for new work in their portal, it shows up here.</p>
    @endforelse
</div>
@endsection
