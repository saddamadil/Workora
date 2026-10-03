@extends('layouts.app')
@section('title', $project->name)
@section('content')
@php $statusTone = ['planning' => 'slate', 'active' => 'green', 'on_hold' => 'amber', 'completed' => 'blue', 'cancelled' => 'slate']; @endphp
<x-page-title :title="$project->name" :sub="($project->client?->name ?? 'No client').($project->deadline ? ' · deadline '.$project->deadline->format('d M Y') : '')">
    <x-pill :tone="$statusTone[$project->status] ?? 'slate'">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</x-pill>
    @if ($canEdit)<a href="{{ route('projects.edit', $project) }}" class="btn-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>@endif
    @can('delete', $project)<form method="POST" action="{{ route('projects.destroy', $project) }}" onsubmit="return confirm('Archive this project?')">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600"><i class="bi bi-archive"></i></button></form>@endcan
</x-page-title>

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Tasks done" :value="$tasks->where('status', 'approved')->count().' / '.$tasks->where('status', '!=', 'cancelled')->count()" icon="bi-check2-square" />
    <x-stat :label="$role->isFreelancer() ? 'Your hours' : 'Hours logged'" :value="hours($minutes)" icon="bi-stopwatch" tone="green" />
    @if ($financials)
        <x-stat label="Budget" :value="$project->budget_minor ? money($project->budget_minor, $project->currency) : 'Not set'" icon="bi-wallet2" tone="slate" />
        <x-stat label="Cost so far (time)" :value="money($costMinor, $project->currency)" icon="bi-graph-up" :tone="$project->budget_minor && $costMinor > $project->budget_minor ? 'red' : 'amber'"
                :hint="$project->budget_minor ? round($costMinor / max(1, $project->budget_minor) * 100).'% of budget' : null" />
    @endif
</div>

@if ($project->description)<p class="card mb-6 whitespace-pre-line p-5 text-sm text-slate-700">{{ $project->description }}</p>@endif

<div class="mb-3 flex items-center justify-between">
    <h2 class="text-lg font-bold text-slate-900">Tasks</h2>
    @if ($canCreateTask)<button type="button" class="btn-primary btn-sm" @click="$dispatch('new-task')"><i class="bi bi-plus-lg"></i> Add task</button>@endif
</div>

<div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
    @foreach ($columns as $title => $statuses)
        @php $col = $tasks->whereIn('status', $statuses); @endphp
        <div class="rounded-2xl bg-slate-100/70 p-3">
            <div class="mb-2 flex items-center justify-between px-1 text-sm font-semibold text-slate-700">{{ $title }} <span class="rounded-full bg-white px-2 text-xs text-slate-500">{{ $col->count() }}</span></div>
            <div class="space-y-2">
                @forelse ($col as $t)
                    <a href="{{ route('tasks.show', $t) }}" class="block rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-brand-500">
                        <div class="text-sm font-medium text-slate-900">{{ $t->title }}</div>
                        <div class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                            <span class="{{ $t->isOverdue() ? 'font-semibold text-red-600' : '' }}">{{ $t->due_at ? $t->due_at->format('d M') : '' }}</span>
                            <span class="truncate">{{ $t->assignees->pluck('name')->map(fn ($n) => explode(' ', $n)[0])->join(', ') ?: 'Unassigned' }}</span>
                        </div>
                    </a>
                @empty
                    <p class="px-1 py-3 text-center text-xs text-slate-400">Empty</p>
                @endforelse
            </div>
        </div>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Team</h2></div>
        <ul class="divide-y divide-slate-100">
            @foreach ($members as $m)
                <li class="flex items-center gap-3 px-5 py-3 text-sm">
                    <span class="grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ strtoupper(substr($m->user->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1"><div class="truncate font-medium text-slate-900">{{ $m->user->name }}</div><div class="text-xs text-slate-500">{{ $m->role_in_project ?: 'Member' }}</div></div>
                    @can('manageMembers', $project)
                        <form method="POST" action="{{ route('projects.members.remove', [$project, $m->user_id]) }}" onsubmit="return confirm('Remove from this project?')">@csrf @method('DELETE')<button class="text-slate-400 hover:text-red-600" aria-label="Remove {{ $m->user->name }}"><i class="bi bi-x-lg"></i></button></form>
                    @endcan
                </li>
            @endforeach
        </ul>
        @can('manageMembers', $project)
            @if ($candidates->isNotEmpty())
                <form method="POST" action="{{ route('projects.members.add', $project) }}" class="flex flex-wrap gap-2 border-t border-slate-100 p-4">
                    @csrf
                    <select name="user_id" class="input min-w-0 flex-1" required aria-label="Person to add"><option value="">Add someone…</option>
                        @foreach ($candidates as $c)<option value="{{ $c->user_id }}">{{ $c->user->name }} ({{ $c->role->label() }})</option>@endforeach</select>
                    <input name="role_in_project" class="input w-36" placeholder="Role, e.g. Designer" aria-label="Role in project">
                    <label class="flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="can_view_budget" value="1" class="rounded border-slate-300"> sees budget</label>
                    <button class="btn-primary btn-sm">Add</button>
                </form>
            @endif
        @endcan
    </div>

    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Files</h2><a href="{{ route('files.index') }}" class="text-xs text-brand-600 hover:underline">All files</a></div>
        @if ($files->isEmpty())<p class="px-5 py-8 text-center text-sm text-slate-500">No files on this project yet. Attach files to a task or upload them from <a class="text-brand-600 hover:underline" href="{{ route('files.index') }}">All files</a>.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($files as $f) @php [$icon, $tint] = $f->icon(); @endphp
                    <li class="flex items-center gap-3 px-5 py-2.5 text-sm"><span class="grid size-8 place-items-center rounded-lg {{ $tint }}"><i class="bi {{ $icon }}"></i></span>
                        <a href="{{ route('files.show', $f) }}" target="_blank" class="min-w-0 flex-1 truncate font-medium text-slate-800 hover:text-brand-600">{{ $f->original_name }}</a>
                        <span class="text-xs text-slate-400">{{ $f->humanSize() }}</span></li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

@if ($canCreateTask)
    @include('tasks._form-modal', ['project' => $project, 'people' => $members->pluck('user')])
@endif
@endsection
