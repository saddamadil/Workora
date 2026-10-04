@extends('layouts.app')
@section('title', 'Request work')
@section('content')
<x-page-title title="Request work" sub="Describe what you need. Your freelancer will accept, decline or discuss it with you." />
<form method="POST" action="{{ route('portal.requests.store') }}" enctype="multipart/form-data" class="card max-w-2xl space-y-4 p-6">@csrf
    <div><label class="label" for="title">What do you need?</label><input id="title" name="title" value="{{ old('title') }}" required maxlength="200" class="input" autofocus>@error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="description">Details</label><textarea id="description" name="description" rows="5" class="input">{{ old('description') }}</textarea></div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="label" for="project_id">Project</label><select id="project_id" name="project_id" class="input"><option value="">Not part of a project yet</option>@foreach ($projects as $p)<option value="{{ $p->id }}" @selected(old('project_id') === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <div><label class="label" for="priority">Priority</label><select id="priority" name="priority" class="input">@foreach (['low', 'medium', 'high', 'urgent'] as $k)<option value="{{ $k }}" @selected(old('priority', 'medium') === $k)>{{ ucfirst($k) }}</option>@endforeach</select></div>
        <div><label class="label" for="preferred_deadline">Preferred deadline</label><input id="preferred_deadline" type="date" name="preferred_deadline" value="{{ old('preferred_deadline') }}" min="{{ now()->toDateString() }}" class="input">@error('preferred_deadline')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
    </div>
    <div><label class="label" for="files">Attachments <span class="font-normal text-slate-400">(optional)</span></label><input id="files" type="file" name="files[]" multiple class="input"></div>
    <div class="flex justify-end gap-2"><a href="{{ route('portal.requests.index') }}" class="btn-secondary">Cancel</a><button class="btn-primary">Submit request</button></div>
</form>
@endsection
