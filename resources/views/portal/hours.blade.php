@extends('layouts.app')
@section('title', 'Hours')
@section('content')
<x-page-title title="Hours" sub="Billable time on the projects where your freelancer shares it with you." />
@if ($projects->isEmpty())
    <div class="card px-5 py-12 text-center text-sm text-slate-500"><i class="bi bi-clock-history text-3xl text-slate-300"></i><p class="mt-2">No project is sharing hours with you yet.</p></div>
@else
<div class="mb-5 grid gap-4 sm:grid-cols-3">
    <div class="card p-5"><p class="text-sm text-slate-500">Total billable</p><p class="text-2xl font-bold text-slate-900">{{ hours($total) }}</p></div>
    @foreach ($perProject->take(2) as $pid => $mins)<div class="card p-5"><p class="truncate text-sm text-slate-500">{{ $projects[$pid]->name ?? 'Project' }}</p><p class="text-2xl font-bold text-slate-900">{{ hours($mins) }}</p></div>@endforeach
</div>
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
    <div><label class="label" for="hp">Project</label><select id="hp" name="project" class="input" onchange="this.form.submit()"><option value="">All projects</option>@foreach ($projects as $p)<option value="{{ $p->id }}" @selected($projectId === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
    <div><label class="label" for="hm">Month</label><select id="hm" name="month" class="input" onchange="this.form.submit()"><option value="">Recent</option>@foreach ($months as $m => $mins)<option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::parse($m.'-01')->format('F Y') }} ({{ hours($mins) }})</option>@endforeach</select></div>
</form>
<div class="card overflow-x-auto">
    <table class="w-full text-sm"><thead class="border-b border-slate-100 text-start text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-2 text-start">Date</th><th class="px-3 py-2 text-start">Project</th><th class="px-3 py-2 text-start">Who</th><th class="px-3 py-2 text-start">Work</th><th class="px-5 py-2 text-end">Hours</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($entries as $e)<tr><td class="whitespace-nowrap px-5 py-2.5">{{ $e->entry_date->format('d M Y') }}</td><td class="px-3 py-2.5">{{ $projects[$e->project_id]->name ?? '' }}</td><td class="px-3 py-2.5">{{ $names[$e->user_id] ?? '' }}</td><td class="px-3 py-2.5 text-slate-600">{{ $e->description }}</td><td class="px-5 py-2.5 text-end font-medium">{{ hours($e->minutes) }}</td></tr>
        @empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No billable time logged yet.</td></tr>@endforelse
        </tbody></table>
</div>
@endif
@endsection
