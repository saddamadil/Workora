@extends('layouts.app')
@section('title', $project->name)
@section('content')
@php $statusTone = ['planning' => 'slate', 'active' => 'green', 'on_hold' => 'amber', 'review' => 'blue', 'revision_requested' => 'orange', 'completed' => 'blue', 'cancelled' => 'slate']; @endphp
<div class="mb-2 text-sm">@if ($project->client)<a href="{{ route('clients.show', $project->client) }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> {{ $project->client->name }}</a>@else<a href="{{ route('projects.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Projects</a>@endif</div>
<x-page-title :title="$project->name" :sub="($project->client?->name ?? 'No client').($project->deadline ? ' · deadline '.$project->deadline->format('d M Y') : '')">
    <x-pill :tone="$statusTone[$project->status] ?? 'slate'">{{ \App\Models\Project::STATUS_LABELS[$project->status] ?? $project->status }}</x-pill>
    @if ($canEdit)<a href="{{ route('projects.edit', $project) }}" class="btn-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>@endif
    @can('delete', $project)<form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Archive this project?')">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600" aria-label="Archive project"><i class="bi bi-archive"></i></button></form>@endcan
</x-page-title>

<div class="mb-4 flex items-center gap-3"><div class="h-2 max-w-xs flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Project progress"><div class="h-full rounded-full bg-brand-500" style="width: {{ $progress }}%"></div></div><span class="text-sm font-semibold text-slate-900">{{ $progress }}% complete</span></div>

<nav class="-mx-1 mb-6 flex gap-1 overflow-x-auto border-b border-slate-200 px-1" aria-label="Project sections">
    @foreach ($tabs as $k => $l)
        <a href="{{ route('projects.show', [$project, 'tab' => $k]) }}" @if ($tab === $k) aria-current="page" @endif
           class="-mb-px shrink-0 border-b-2 px-3 py-2.5 text-sm font-medium {{ $tab === $k ? 'border-brand-500 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800' }}">{{ $l }}</a>
        @if ($k === 'tasks' && $project->client_id)
            <a href="{{ route('messages.index', ['client' => $project->client_id, 'project' => $project->id]) }}" class="-mb-px shrink-0 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-800">Messages</a>
        @endif
    @endforeach
</nav>

@include('projects.tabs.'.$tab)

@if ($canCreateTask)
    @include('tasks._form-modal', ['project' => $project, 'people' => $members->pluck('user')])
@endif
@endsection
