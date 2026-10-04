@extends('layouts.app')
@section('title', 'Requests')
@section('content')
@php $tone = ['new' => 'blue', 'discussing' => 'amber', 'accepted' => 'green', 'in_progress' => 'indigo', 'completed' => 'green', 'declined' => 'slate']; @endphp
<x-page-title title="Requests" sub="Ask for new work and see exactly what happened to each request."><a href="{{ route('portal.requests.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> Request work</a></x-page-title>
<div class="card divide-y divide-slate-100">
    @forelse ($requests as $r)
        <a href="{{ route('portal.requests.show', $r) }}" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50"><div class="min-w-0 flex-1"><div class="font-semibold text-slate-900">{{ $r->title }}</div><div class="text-sm text-slate-500">Sent {{ $r->created_at->format('d M Y') }}@if ($r->preferred_deadline) · wanted by {{ $r->preferred_deadline->format('d M') }}@endif</div></div><x-pill :tone="$tone[$r->status] ?? 'slate'">{{ \App\Models\ClientRequest::STATUSES[$r->status] }}</x-pill></a>
    @empty
        <div class="px-5 py-12 text-center text-sm text-slate-500">No requests yet. Need something new? Tell your freelancer what you need.<div class="mt-3"><a href="{{ route('portal.requests.create') }}" class="btn-primary btn-sm">Request work</a></div></div>
    @endforelse
</div>
@endsection
