@extends('layouts.app')
@section('title', $project->name)
@section('content')
@php
    $tone = ['active' => 'green', 'planning' => 'blue', 'on_hold' => 'amber', 'review' => 'blue', 'revision_requested' => 'orange', 'completed' => 'slate'];
    $tabs = ['overview' => 'Overview', 'tasks' => 'Tasks', 'milestones' => 'Milestones', 'files' => 'Files', 'activity' => 'Activity'];
@endphp
<div class="mb-2 text-sm"><a href="{{ route('portal.projects') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Our projects</a></div>
<x-page-title :title="$project->name" :sub="$project->deadline ? 'Deadline '.$project->deadline->format('d M Y') : null">
    <x-pill :tone="$tone[$project->status] ?? 'slate'">{{ \App\Models\Project::STATUS_LABELS[$project->status] ?? $project->status }}</x-pill>
    <a href="{{ route('portal.messages.index', ['project' => $project->id]) }}" class="btn-secondary btn-sm"><i class="bi bi-chat-dots"></i> Messages</a>
</x-page-title>
<div class="mb-4 flex items-center gap-3"><div class="h-2.5 max-w-sm flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress"><div class="h-full rounded-full bg-brand-500" style="width: {{ $progress }}%"></div></div><span class="font-semibold text-slate-900">{{ $progress }}%</span><span class="text-sm text-slate-500">{{ $done }} of {{ $tasks->count() }} tasks done</span></div>
<nav class="-mx-1 mb-6 flex gap-1 overflow-x-auto border-b border-slate-200 px-1" aria-label="Project sections">
    @foreach ($tabs as $k => $l)<a href="{{ route('portal.project', [$project->slug, 'tab' => $k]) }}" @if ($tab === $k) aria-current="page" @endif class="-mb-px shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium {{ $tab === $k ? 'border-brand-500 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $l }}</a>@endforeach
</nav>

@if ($tab === 'overview')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($project->description)<p class="card whitespace-pre-line p-5 text-sm text-slate-700">{{ $project->description }}</p>@endif
            @include('portal._deliverables', ['deliverables' => $deliverables])
        </div>
        <div class="space-y-6">
            @if ($hours !== null)<div class="card p-5 text-sm"><span class="text-slate-500">Billable hours so far</span><div class="text-xl font-bold text-slate-900">{{ hours($hours) }}</div></div>@endif
            <div class="card"><div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Milestones</h2></div>
                @forelse ($milestones as $m)<div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><i class="bi {{ $m->status === 'completed' ? 'bi-check-circle-fill text-emerald-500' : ($m->status === 'in_progress' ? 'bi-play-circle-fill text-brand-500' : 'bi-circle text-slate-300') }} text-lg"></i><span class="flex-1">{{ $m->title }}</span><span class="text-slate-500">{{ \App\Models\ProjectMilestone::STATUSES[$m->status] }}</span></div>
                @empty<p class="px-5 py-6 text-center text-sm text-slate-500">No milestones yet.</p>@endforelse</div>
        </div>
    </div>
@elseif ($tab === 'tasks')
    <div class="card divide-y divide-slate-100">
        @forelse ($tasks as $t)<div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm"><span class="min-w-0 flex-1 text-slate-900">{{ $t->title }}</span>@if ($t->due_at)<span class="text-slate-500">Due {{ $t->due_at->format('d M') }}</span>@endif<span class="rounded-full {{ \App\Models\Task::STATUS_STYLES[$t->status] ?? '' }} px-2.5 py-0.5 text-xs font-medium">{{ \App\Models\Task::STATUS_LABELS[$t->status] ?? $t->status }}</span></div>
        @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No tasks yet.</p>@endforelse
    </div>
@elseif ($tab === 'milestones')
    <div class="space-y-6">
        <div class="card divide-y divide-slate-100">
            @forelse ($milestones as $m)<div class="flex items-center gap-3 px-5 py-4 text-sm"><i class="bi {{ $m->status === 'completed' ? 'bi-check-circle-fill text-emerald-500' : ($m->status === 'in_progress' ? 'bi-play-circle-fill text-brand-500' : 'bi-circle text-slate-300') }} text-xl"></i><div class="min-w-0 flex-1"><div class="font-medium text-slate-900">{{ $m->title }}</div><div class="text-xs text-slate-500">{{ $m->due_date ? 'Due '.$m->due_date->format('d M Y') : '' }}</div></div><x-pill :tone="$m->status === 'completed' ? 'green' : 'slate'">{{ \App\Models\ProjectMilestone::STATUSES[$m->status] }}</x-pill></div>
            @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No milestones yet.</p>@endforelse
        </div>
        @include('portal._deliverables', ['deliverables' => $deliverables])
    </div>
@elseif ($tab === 'files')
    <div class="card divide-y divide-slate-100">
        @forelse ($files as $f)<a href="{{ route('portal.files.show', $f) }}" target="_blank" class="flex items-center gap-3 px-5 py-3 text-sm hover:bg-slate-50"><i class="bi {{ $f->icon()[0] }} text-lg text-slate-400"></i><span class="min-w-0 flex-1 truncate font-medium text-slate-900">{{ $f->original_name }}</span><span class="text-xs text-slate-500">{{ $f->humanSize() }}</span></a>
        @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No shared files on this project yet. <a class="text-brand-600 underline" href="{{ route('portal.files.index') }}">Upload one</a>.</p>@endforelse
    </div>
@else
    <div class="card">
        @forelse ($activity as $a)<div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="text-slate-800">{{ ucfirst(str_replace(['.', '_'], ' ', $a->action)) }}@if ($a->user) <span class="text-slate-500">· {{ $a->user->name }}</span>@endif</span><time class="shrink-0 text-xs text-slate-500">{{ $a->created_at->format('d M, H:i') }}</time></div>
        @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No activity yet.</p>@endforelse
    </div>
@endif
@endsection
