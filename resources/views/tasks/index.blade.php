@extends('layouts.app')
@section('title', $role->isFreelancer() ? 'My tasks' : 'Tasks')
@section('content')
@php
    $q = fn (array $x) => route('tasks.index', array_filter(array_merge(request()->only('q', 'scope', 'mine', 'project', 'view', 'month'), $x), fn ($v) => $v !== null && $v !== ''));
    $columns = ['todo' => ['To do', ['backlog', 'assigned']], 'progress' => ['In progress', ['in_progress']], 'review' => ['Review', ['submitted', 'under_review', 'revision_required']], 'done' => ['Completed', ['approved']]];
    $prio = ['urgent' => 'border-l-red-500', 'high' => 'border-l-orange-500', 'medium' => 'border-l-sky-500', 'low' => 'border-l-slate-300'];
@endphp
<x-page-title :title="$role->isFreelancer() ? 'My tasks' : 'Tasks'" sub="Everything you can see across your projects." />

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center">
    <div class="flex flex-wrap gap-2">
        @if ($view === 'list')
        <a href="{{ $q(['scope' => null]) }}" class="chip {{ $scope === 'open' ? 'chip-active' : '' }}">Open</a>
        <a href="{{ $q(['scope' => 'review']) }}" class="chip {{ $scope === 'review' ? 'chip-active' : '' }}">In review @if ($reviewCount)<span class="rounded-full bg-amber-100 px-1.5 text-xs text-amber-800">{{ $reviewCount }}</span>@endif</a>
        <a href="{{ $q(['scope' => 'done']) }}" class="chip {{ $scope === 'done' ? 'chip-active' : '' }}">Approved</a>
        @endif
        @unless ($role->isFreelancer())<a href="{{ $q(['mine' => $mine ? null : 1]) }}" class="chip {{ $mine ? 'chip-active' : '' }}"><i class="bi bi-person"></i> Assigned to me</a>@endunless
    </div>
    <div class="flex items-center gap-2 lg:ms-auto">
        <div class="flex gap-1" role="tablist" aria-label="View">@foreach (['list' => ['List', 'bi-list-ul'], 'board' => ['Board', 'bi-kanban'], 'calendar' => ['Calendar', 'bi-calendar3']] as $k => [$l, $ic])<a href="{{ $q(['view' => $k === 'list' ? null : $k, 'month' => null]) }}" role="tab" aria-selected="{{ $view === $k ? 'true' : 'false' }}" class="chip {{ $view === $k ? 'chip-active' : '' }}"><i class="bi {{ $ic }}"></i> {{ $l }}</a>@endforeach</div>
        <form method="GET" class="relative w-56">
            @foreach (request()->only('scope', 'mine', 'project', 'view') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <i class="bi bi-search pointer-events-none absolute start-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search tasks" class="input ps-10" aria-label="Search tasks">
        </form>
    </div>
</div>

@if ($view === 'board')
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" x-data="{
        drag: null, msg: '',
        async drop(col) {
            if (!this.drag) return;
            const el = document.querySelector('[data-task=\'' + this.drag + '\']'); const target = document.querySelector('[data-col=\'' + col + '\'] [data-list]');
            const r = await fetch('{{ url('/tasks') }}/' + this.drag + '/move', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ column: col }) });
            if (r.ok) { target.appendChild(el); this.msg = ''; } else { this.msg = (await r.json()).message || 'That move is not allowed.'; }
            this.drag = null;
        } }">
    <p x-show="msg" x-cloak x-text="msg" class="md:col-span-2 xl:col-span-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900" role="alert"></p>
    @foreach ($columns as $key => [$title, $statuses])
        @php $col = $tasks->whereIn('status', $statuses); @endphp
        <section class="rounded-xl bg-slate-100/70 p-3" data-col="{{ $key }}" aria-label="{{ $title }}" @dragover.prevent @drop.prevent="drop('{{ $key }}')">
            <div class="mb-2 flex items-center justify-between px-1 text-sm font-semibold text-slate-700">{{ $title }} <span class="rounded-full bg-white px-2 text-xs text-slate-500">{{ $col->count() }}</span></div>
            <div class="min-h-24 space-y-2" data-list>
                @foreach ($col as $t)
                    <a href="{{ route('tasks.show', $t) }}" data-task="{{ $t->id }}" draggable="true" @dragstart="drag = '{{ $t->id }}'" class="block cursor-grab rounded-lg border border-s-4 border-slate-200 bg-white p-3 text-sm shadow-sm hover:border-slate-300 {{ $prio[$t->priority] ?? '' }}">
                        <div class="font-medium text-slate-900">{{ $t->title }}</div>
                        <div class="mt-1 flex items-center justify-between gap-2 text-xs text-slate-500"><span class="truncate">{{ $t->project->name }}</span>@if ($t->due_at)<span class="{{ $t->isOverdue() ? 'font-semibold text-red-600' : '' }}">{{ $t->due_at->format('d M') }}</span>@endif</div>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
    <p class="md:col-span-2 xl:col-span-4 text-xs text-slate-500">Drag a card to another column. {{ $canMoveAnywhere ? 'On your own workspace you can move tasks anywhere.' : 'You can move your own open tasks between To do and In progress. Review and approval happen on the task page.' }}</p>
</div>
@elseif ($view === 'calendar')
@php $cursor = $month->copy()->startOfWeek(); $end = $month->copy()->endOfMonth()->endOfWeek(); $by = $tasks->groupBy(fn ($t) => $t->due_at->toDateString()); @endphp
<div class="mb-3 flex items-center gap-2"><a class="btn-secondary btn-sm" href="{{ $q(['month' => $month->copy()->subMonth()->format('Y-m-d')]) }}" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a><a class="btn-secondary btn-sm" href="{{ $q(['month' => now()->startOfMonth()->format('Y-m-d')]) }}">Today</a><a class="btn-secondary btn-sm" href="{{ $q(['month' => $month->copy()->addMonth()->format('Y-m-d')]) }}" aria-label="Next month"><i class="bi bi-chevron-right"></i></a><h2 class="ms-2 text-lg font-bold text-slate-900">{{ $month->format('F Y') }}</h2></div>
<div class="card overflow-hidden"><div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-xs font-semibold uppercase text-slate-500">@foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)<div class="py-2">{{ $d }}</div>@endforeach</div>
    <div class="grid grid-cols-7">
        @for ($d = $cursor->copy(); $d->lte($end); $d->addDay())
            @php $list = $by[$d->toDateString()] ?? collect(); @endphp
            <div class="min-h-24 border-b border-e border-slate-100 p-1.5 {{ $d->month === $month->month ? '' : 'bg-slate-50/60' }}"><span class="inline-grid size-6 place-items-center rounded-full text-xs {{ $d->isToday() ? 'bg-brand-500 font-bold text-slate-900' : 'text-slate-600' }}">{{ $d->day }}</span>
                @foreach ($list->take(3) as $t)<a href="{{ route('tasks.show', $t) }}" class="mt-0.5 block truncate rounded border-s-2 bg-sky-50 px-1 py-0.5 text-[11px] text-sky-900 {{ $prio[$t->priority] ?? '' }}" title="{{ $t->title }}">{{ $t->title }}</a>@endforeach
                @if ($list->count() > 3)<span class="text-[11px] text-slate-500">+{{ $list->count() - 3 }} more</span>@endif</div>
        @endfor
    </div></div>
<p class="mt-2 text-xs text-slate-500">Only tasks with a due date appear here.</p>
@else
<div class="card">@include('tasks._list', ['tasks' => $tasks, 'empty' => 'No tasks match.'])</div>
<div class="mt-6">{{ $tasks->links() }}</div>
@endif
@endsection
