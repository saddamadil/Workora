@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
@php $icons = ['message' => 'bi-chat-dots', 'request' => 'bi-inbox', 'task' => 'bi-check2-square', 'project' => 'bi-kanban', 'file' => 'bi-file-earmark', 'approval' => 'bi-patch-check', 'revision' => 'bi-arrow-repeat', 'invoice' => 'bi-receipt', 'payment' => 'bi-cash-coin']; @endphp
<x-page-title title="Notifications" sub="What happened since you last looked.">
    <a href="{{ route('notifications.preferences') }}" class="btn-secondary btn-sm"><i class="bi bi-sliders"></i> Settings</a>
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-secondary btn-sm">Mark all as read</button></form>
</x-page-title>
<div class="mb-4 flex gap-2"><a href="{{ route('notifications.index') }}" class="chip {{ $filter === 'all' ? 'chip-active' : '' }}">All</a><a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="chip {{ $filter === 'unread' ? 'chip-active' : '' }}">Unread</a></div>
<div class="card divide-y divide-slate-100">
    @forelse ($items as $n)
        <a href="{{ route('notifications.open', $n) }}" class="flex items-start gap-3 px-5 py-4 hover:bg-slate-50 {{ $n->read_at ? '' : 'bg-brand-50/40' }}">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600"><i class="bi {{ $icons[$n->type] ?? 'bi-bell' }}"></i></span>
            <div class="min-w-0 flex-1"><div class="text-sm {{ $n->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $n->title }}@unless ($n->read_at)<span class="sr-only"> (unread)</span>@endunless</div>@if ($n->body)<div class="truncate text-sm text-slate-500">{{ $n->body }}</div>@endif</div>
            <time class="shrink-0 text-xs text-slate-500" datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->diffForHumans() }}</time>
        </a>
    @empty
        <p class="px-5 py-14 text-center text-sm text-slate-500">You are all caught up. New messages, approvals and payments show up here.</p>
    @endforelse
</div>
@endsection
