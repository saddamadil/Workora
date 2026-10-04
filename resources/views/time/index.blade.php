@extends('layouts.app')
@section('title', 'Time')
@section('content')
@php
    $statusTone = ['draft' => 'slate', 'submitted' => 'amber', 'approved' => 'green', 'rejected' => 'orange'];
    $cur = $org->base_currency;
@endphp
<x-page-title title="Time" sub="Track what you work on, then send the week for approval." />

{{-- Timer --}}
<div class="card mb-6 p-5" x-data="{ project: '', tasks: @js($tasks->map(fn ($t) => ['id' => $t->id, 'project_id' => $t->project_id, 'title' => $t->title])->values()) }">
    @if ($running)
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center" x-data="{ secs: {{ (int) $running->started_at->diffInSeconds(now()) }}, get label() { const h = Math.floor(this.secs / 3600), m = Math.floor(this.secs % 3600 / 60), s = this.secs % 60; return [h, m, s].map(n => String(n).padStart(2, '0')).join(':') } }" x-init="setInterval(() => secs++, 1000)">
            <span class="grid size-12 place-items-center rounded-2xl bg-emerald-50 text-2xl text-emerald-600"><i class="bi bi-stopwatch"></i></span>
            <div class="min-w-0 flex-1">
                <div class="font-mono text-3xl font-bold text-slate-900" x-text="label" aria-live="off"></div>
                <div class="truncate text-sm text-slate-500">{{ $running->project->name }}@if ($running->task) · {{ $running->task->title }}@endif @if ($running->description) · {{ $running->description }}@endif</div>
            </div>
            <form method="POST" action="{{ route('time.stop') }}">@csrf<button class="btn-danger px-6"><i class="bi bi-stop-fill"></i> Stop</button></form>
        </div>
    @else
        <form method="POST" action="{{ route('time.start') }}" class="grid gap-3 sm:grid-cols-12">
            @csrf
            <div class="sm:col-span-4"><label class="label" for="tp">Project</label>
                <select id="tp" name="project_id" x-model="project" required class="input"><option value="">Choose…</option>@foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
            <div class="sm:col-span-4"><label class="label" for="tt">Task <span class="font-normal text-slate-400">(optional)</span></label>
                <select id="tt" name="task_id" class="input"><option value="">No specific task</option><template x-for="t in tasks.filter(t => t.project_id === project)" :key="t.id"><option :value="t.id" x-text="t.title"></option></template></select></div>
            <div class="sm:col-span-4"><label class="label" for="td">Note</label><input id="td" name="description" class="input" placeholder="What are you working on?" maxlength="500"></div>
            <div class="sm:col-span-12 flex justify-end"><button class="btn-primary" @disabled($projects->isEmpty())><i class="bi bi-play-fill"></i> Start timer</button></div>
        </form>
        @if ($projects->isEmpty())<p class="mt-2 text-sm text-slate-500">You are not on an active project yet.</p>@endif
    @endif
</div>

{{-- Week --}}
<div class="mb-4 flex flex-wrap items-center gap-3">
    <a href="{{ route('time.index', ['week' => $weekStart->copy()->subWeek()->toDateString()]) }}" class="btn-secondary btn-sm" aria-label="Previous week"><i class="bi bi-chevron-left"></i></a>
    <div class="font-semibold text-slate-900">{{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}</div>
    <a href="{{ route('time.index', ['week' => $weekStart->copy()->addWeek()->toDateString()]) }}" class="btn-secondary btn-sm" aria-label="Next week"><i class="bi bi-chevron-right"></i></a>
    @if (! $weekStart->isCurrentWeek())<a href="{{ route('time.index') }}" class="text-sm text-brand-600 hover:underline">This week</a>@endif
    <div class="ms-auto flex items-center gap-3 text-sm">
        <span class="text-slate-500">Total <strong class="text-slate-900">{{ hours($totalMinutes) }}</strong></span>
        @if ($showMoney && $estimateMinor)<span class="text-slate-500">Value <strong class="text-slate-900">{{ money($estimateMinor, $cur) }}</strong></span>@endif
        @if ($timesheet)<x-pill :tone="$statusTone[$timesheet->status] ?? 'slate'">{{ ucfirst($timesheet->status) }}</x-pill>@endif
    </div>
</div>
@if ($timesheet?->status === 'rejected' && $timesheet->review_note)
    <div class="mb-4 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900"><strong>Sent back:</strong> {{ $timesheet->review_note }}</div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        @foreach ($days as $day)
            @php $dayEntries = $entries->filter(fn ($e) => $e->entry_date->isSameDay($day)); @endphp
            <div class="border-b border-slate-100 last:border-0">
                <div class="flex items-center justify-between bg-slate-50/70 px-5 py-2 text-sm">
                    <span class="font-semibold {{ $day->isToday() ? 'text-brand-700' : 'text-slate-700' }}">{{ $day->format('l, d M') }}</span>
                    <span class="text-slate-500">{{ $dayEntries->isEmpty() ? '' : hours((int) $dayEntries->sum('minutes')) }}</span>
                </div>
                @foreach ($dayEntries as $e)
                    <div class="flex items-center gap-3 px-5 py-2.5 text-sm">
                        <div class="min-w-0 flex-1"><div class="truncate font-medium text-slate-900">{{ $e->project->name }}@if ($e->task) <span class="font-normal text-slate-500">· {{ $e->task->title }}</span>@endif</div>
                            @if ($e->description)<div class="truncate text-xs text-slate-500">{{ $e->description }}</div>@endif</div>
                        @unless ($e->is_billable)<x-pill>Non-billable</x-pill>@endunless
                        @if ($e->source === 'timer')<i class="bi bi-stopwatch text-slate-400" title="Recorded with the timer"></i>@endif
                        <span class="font-medium text-slate-900">{{ hours($e->minutes) }}</span>
                        @if ($editable && ! $e->isLocked())
                            <form method="POST" action="{{ route('time.destroy', $e) }}" onsubmit="return confirm('Remove this entry?')">@csrf @method('DELETE')<button class="text-slate-300 hover:text-red-600" aria-label="Remove entry"><i class="bi bi-trash"></i></button></form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <div class="space-y-6">
        @if ($editable)
            <form method="POST" action="{{ route('time.store') }}" class="card space-y-3 p-5" x-data="{ project: '{{ old('project_id') }}', tasks: @js($tasks->map(fn ($t) => ['id' => $t->id, 'project_id' => $t->project_id, 'title' => $t->title])->values()) }">
                @csrf
                <h2 class="font-semibold text-slate-900">Add time</h2>
                <div><label class="label" for="mp">Project</label><select id="mp" name="project_id" x-model="project" required class="input"><option value="">Choose…</option>@foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                <div><label class="label" for="mt">Task</label><select id="mt" name="task_id" class="input"><option value="">No specific task</option><template x-for="t in tasks.filter(t => t.project_id === project)" :key="t.id"><option :value="t.id" x-text="t.title"></option></template></select></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label" for="md">Date</label><input id="md" type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input"></div>
                    <div><label class="label" for="mh">Time</label><input id="mh" name="duration" value="{{ old('duration') }}" required class="input" placeholder="1:30 or 1.5" inputmode="decimal"></div>
                </div>
                <div><label class="label" for="mn">Note</label><input id="mn" name="description" class="input" maxlength="500"></div>
                <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="is_billable" value="1" checked class="rounded border-slate-300"> Billable</label>
                <div class="flex items-center gap-2 text-sm text-slate-600"><label for="mr">Rate per hour</label><input id="mr" name="rate" type="number" step="0.01" min="0" class="input w-28 py-1.5" placeholder="Default"></div>
                <button class="btn-primary w-full" @disabled($projects->isEmpty())>Add entry</button>
            </form>
        @endif

        <div class="card p-5 text-sm">
            <h2 class="mb-2 font-semibold text-slate-900">{{ now()->format('F') }} so far</h2>
            <dl class="grid grid-cols-2 gap-2"><div><dt class="text-xs text-slate-500">Hours</dt><dd class="font-semibold text-slate-900">{{ hours($month['total']) }}</dd></div><div><dt class="text-xs text-slate-500">Billable</dt><dd class="font-semibold text-slate-900">{{ hours($month['billable']) }}</dd></div>
                <div class="col-span-2"><dt class="text-xs text-slate-500">Worth</dt><dd class="font-semibold text-slate-900">{{ $month['value'] ? collect($month['value'])->map(fn ($m, $c) => money($m, $c))->implode(' + ') : '—' }}</dd></div></dl>
        </div>

        @if (! $solo && $editable && $entries->isNotEmpty())
            <form method="POST" action="{{ route('time.submit-week') }}" class="card p-5">@csrf
                <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                <h2 class="font-semibold text-slate-900">Done for the week?</h2>
                <p class="mt-1 text-sm text-slate-500">Send {{ hours($totalMinutes) }} to the company for approval. You can add time again only if they send it back.</p>
                <button class="btn-primary mt-3 w-full"><i class="bi bi-send"></i> Submit week</button>
            </form>
        @elseif ($timesheet && ! $editable)
            <div class="card p-5 text-sm text-slate-600">This week is <strong>{{ $timesheet->status }}</strong>@if ($timesheet->reviewed_at) · reviewed {{ $timesheet->reviewed_at->diffForHumans() }}@endif.</div>
        @endif
    </div>
</div>
@endsection
