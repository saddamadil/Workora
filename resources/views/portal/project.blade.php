@extends('layouts.app')
@section('title', $project['name'])
@section('content')
@php $tone = ['active' => 'green', 'planning' => 'blue', 'on_hold' => 'amber', 'completed' => 'slate']; @endphp
<div class="mb-2 text-sm"><a href="{{ route('portal.projects') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Our projects</a></div>
<x-page-title :title="$project['name']" :sub="$project['deadline'] ? 'Deadline '.$project['deadline']->format('d M Y') : null"><x-pill :tone="$tone[$project['status']] ?? 'slate'">{{ ucfirst(str_replace('_', ' ', $project['status'])) }}</x-pill></x-page-title>
<div class="mb-6 card p-5">
    <div class="flex items-center gap-3"><div class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress"><div class="h-full rounded-full bg-brand-500" style="width: {{ $progress }}%"></div></div><span class="font-semibold text-slate-900">{{ $progress }}%</span></div>
    <p class="mt-1 text-sm text-slate-500">{{ $done }} of {{ $tasks->count() }} tasks approved</p>
    @if ($project['description'])<p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $project['description'] }}</p>@endif
</div>
<section class="card" aria-labelledby="t">
    <div class="border-b border-slate-100 px-5 py-3"><h2 id="t" class="font-semibold text-slate-900">Tasks</h2></div>
    @forelse ($tasks as $t)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
            <span class="min-w-0 flex-1 text-slate-900">{{ $t->title }}</span>
            @if ($t->due_at)<span class="text-slate-500">Due {{ $t->due_at->format('d M') }}</span>@endif
            <span class="rounded-full {{ \App\Models\Task::STATUS_STYLES[$t->status] ?? '' }} px-2.5 py-0.5 text-xs font-medium">{{ \App\Models\Task::STATUS_LABELS[$t->status] ?? $t->status }}</span>
        </div>
    @empty<p class="px-5 py-8 text-center text-sm text-slate-500">No tasks yet.</p>@endforelse
</section>
@endsection
