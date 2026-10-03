@extends('layouts.app')
@section('title', 'Projects')
@section('content')
@php $statusTone = ['planning' => 'slate', 'active' => 'green', 'on_hold' => 'amber', 'completed' => 'blue', 'cancelled' => 'slate']; @endphp
<x-page-title :title="$role->isFreelancer() ? 'My projects' : 'Projects'" sub="Everything in progress and everything finished.">
    @can('create', \App\Models\Project::class)<a href="{{ route('projects.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> New project</a>@endcan
</x-page-title>

<form method="GET" class="mb-5 flex flex-col gap-3 sm:flex-row">
    <div class="relative flex-1"><i class="bi bi-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="search" name="q" value="{{ $search }}" placeholder="Search projects" class="input pl-10" aria-label="Search projects"></div>
    <select name="status" class="input sm:w-48" onchange="this.form.submit()" aria-label="Status">
        <option value="">All statuses</option>
        @foreach (\App\Models\Project::STATUSES as $s)<option value="{{ $s }}" @selected($status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach
    </select>
</form>

@if ($projects->isEmpty())
    <div class="card px-6 py-12 text-center">
        <i class="bi bi-kanban text-4xl text-slate-300"></i>
        <p class="mt-3 font-semibold text-slate-700">{{ $role->isFreelancer() ? 'You have not been added to a project yet.' : 'No projects yet.' }}</p>
        @can('create', \App\Models\Project::class)<a href="{{ route('projects.create') }}" class="btn-primary mt-4">Create your first project</a>@endcan
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($projects as $p)
            @php $pct = $p->tasks_total ? round($p->tasks_done / $p->tasks_total * 100) : 0; @endphp
            <a href="{{ route('projects.show', $p) }}" class="card block p-5 transition hover:border-brand-500">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="truncate font-semibold text-slate-900">{{ $p->name }}</h2>
                    <x-pill :tone="$statusTone[$p->status] ?? 'slate'">{{ ucfirst(str_replace('_', ' ', $p->status)) }}</x-pill>
                </div>
                <p class="mt-0.5 truncate text-sm text-slate-500">{{ $p->client?->name ?? 'No client' }}</p>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-600" style="width: {{ $pct }}%"></div></div>
                <div class="mt-2 flex justify-between text-xs text-slate-500">
                    <span>{{ $p->tasks_done }}/{{ $p->tasks_total }} tasks done</span>
                    <span>{{ $p->members_count }} <i class="bi bi-people"></i></span>
                </div>
                @if ($p->deadline)<div class="mt-2 text-xs {{ $p->deadline->isPast() && $p->status === 'active' ? 'font-semibold text-red-600' : 'text-slate-500' }}"><i class="bi bi-calendar-event"></i> Deadline {{ $p->deadline->format('d M Y') }}</div>@endif
            </a>
        @endforeach
    </div>
    <div class="mt-6">{{ $projects->links() }}</div>
@endif
@endsection
