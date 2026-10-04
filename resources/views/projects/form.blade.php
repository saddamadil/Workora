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
            <select id="status" name="status" class="input">@foreach (\App\Models\Project::STATUSES as $s)<option value="{{ $s }}" @selected(old('status', $project->status) === $s)>{{ \App\Models\Project::STATUS_LABELS[$s] }}</option>@endforeach</select></div>
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
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="label" for="billing_model">Billing</label>
            <select id="billing_model" name="billing_model" class="input">@foreach (['hourly' => 'Hourly', 'fixed' => 'Fixed price', 'recurring' => 'Recurring'] as $k => $l)<option value="{{ $k }}" @selected(old('billing_model', $project->billing_model ?: 'hourly') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="label" for="priority">Priority</label>
            <select id="priority" name="priority" class="input">@foreach (['low', 'medium', 'high', 'urgent'] as $k)<option value="{{ $k }}" @selected(old('priority', $project->priority ?: 'medium') === $k)>{{ ucfirst($k) }}</option>@endforeach</select></div>
        <div><label class="label" for="tags">Tags</label><input id="tags" name="tags" value="{{ old('tags', $project->tags) }}" class="input" placeholder="seo, monthly"></div>
    </div>
    @if ($org->mode !== 'solo')
    <div><label class="label" for="project_manager_id">Project manager</label>
        <select id="project_manager_id" name="project_manager_id" class="input"><option value="">Not set</option>
            @foreach ($managers as $m)<option value="{{ $m->user_id }}" @selected(old('project_manager_id', $project->project_manager_id) === $m->user_id)>{{ $m->user->name }}</option>@endforeach</select></div>
    @endif
    <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="share_hours" value="1" class="mt-1 rounded border-slate-300" @checked(old('share_hours', $project->share_hours))> <span>Show logged hours to the client<span class="block text-xs text-slate-500">Off by default. The client sees progress either way.</span></span></label>
    <div class="flex justify-end gap-2">
        <a href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}" class="btn-secondary">Cancel</a>
        <button class="btn-primary">{{ $project->exists ? 'Save changes' : 'Create project' }}</button>
    </div>
</form>
@endsection
