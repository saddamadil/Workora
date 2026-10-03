@extends('layouts.app')
@section('title', $project->exists ? 'Edit project' : 'New project')
@section('content')
<x-page-title :title="$project->exists ? 'Edit project' : 'New project'" />
<form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="card max-w-3xl space-y-5 p-6">
    @csrf @if ($project->exists) @method('PUT') @endif
    <div><label class="label" for="name">Project name</label><input id="name" name="name" value="{{ old('name', $project->name) }}" required class="input" autofocus></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="client_id">Client</label>
            <select id="client_id" name="client_id" class="input"><option value="">No client</option>
                @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $project->client_id) === $c->id)>{{ $c->name }}</option>@endforeach</select>
            @if ($clients->isEmpty())<p class="mt-1 text-xs text-slate-500">No clients yet. <a class="text-brand-600 hover:underline" href="{{ route('clients.index') }}">Add one</a>.</p>@endif</div>
        <div><label class="label" for="status">Status</label>
            <select id="status" name="status" class="input">@foreach (\App\Models\Project::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $project->status) === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach</select></div>
    </div>
    <div><label class="label" for="description">Description</label><textarea id="description" name="description" rows="4" class="input">{{ old('description', $project->description) }}</textarea></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="start_date">Start date</label><input id="start_date" type="date" name="start_date" value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}" class="input"></div>
        <div><label class="label" for="deadline">Deadline</label><input id="deadline" type="date" name="deadline" value="{{ old('deadline', $project->deadline?->format('Y-m-d')) }}" class="input"></div>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2"><label class="label" for="budget">Budget</label><input id="budget" name="budget" type="number" step="0.01" min="0" value="{{ old('budget', \App\Support\Money::toInput($project->budget_minor)) }}" class="input" placeholder="Optional"></div>
        <div><label class="label" for="currency">Currency</label>
            <select id="currency" name="currency" class="input">@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected(old('currency', $project->currency ?: $org->base_currency) === $c)>{{ $c }}</option>@endforeach</select></div>
    </div>
    <div><label class="label" for="project_manager_id">Project manager</label>
        <select id="project_manager_id" name="project_manager_id" class="input"><option value="">Not set</option>
            @foreach ($managers as $m)<option value="{{ $m->user_id }}" @selected(old('project_manager_id', $project->project_manager_id) === $m->user_id)>{{ $m->user->name }}</option>@endforeach</select></div>
    <div class="flex justify-end gap-2">
        <a href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}" class="btn-secondary">Cancel</a>
        <button class="btn-primary">{{ $project->exists ? 'Save changes' : 'Create project' }}</button>
    </div>
</form>
@endsection
