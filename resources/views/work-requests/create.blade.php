@extends('layouts.app')
@section('title', 'New proposal')
@section('content')
<x-page-title title="New proposal" sub="Describe the work and the price you have in mind. The company can accept or counter." />
<form method="POST" action="{{ route('work-requests.store') }}" class="card max-w-2xl space-y-5 p-6">@csrf
    <div><label class="label" for="title">What would you do?</label><input id="title" name="title" value="{{ old('title') }}" required class="input" autofocus></div>
    <div><label class="label" for="description">Details</label><textarea id="description" name="description" rows="5" required class="input">{{ old('description') }}</textarea></div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="label" for="amount">Your price ({{ $org->base_currency }})</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required class="input"></div>
        <div><label class="label" for="estimated_hours">Hours</label><input id="estimated_hours" name="estimated_hours" type="number" step="0.5" min="0" value="{{ old('estimated_hours') }}" class="input"></div>
        <div><label class="label" for="proposed_deadline">Finish by</label><input id="proposed_deadline" type="date" name="proposed_deadline" value="{{ old('proposed_deadline') }}" class="input"></div>
    </div>
    <div><label class="label" for="project_id">Related project <span class="font-normal text-slate-400">(optional)</span></label>
        <select id="project_id" name="project_id" class="input"><option value="">Not tied to a project</option>@foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
    <div class="flex justify-end gap-2"><a href="{{ route('work-requests.index') }}" class="btn-secondary">Cancel</a><button class="btn-primary">Send proposal</button></div>
</form>
@endsection
