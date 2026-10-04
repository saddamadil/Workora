@extends('layouts.app')
@section('title', $agreement->exists ? 'Edit agreement' : 'New agreement')
@section('content')
<x-page-title :title="$agreement->exists ? 'Edit agreement' : 'New agreement'" sub="Plain templates to start from. They are not legal advice: have a professional review anything important." />
<form method="POST" action="{{ $agreement->exists ? route('agreements.update', $agreement) : route('agreements.store') }}" class="card space-y-4 p-5" x-data="{ clientId: '{{ old('client_id', $agreement->client_id) }}', projects: @js($projects), templates: @js($templates), body: @js(old('body', $agreement->body)), title: @js(old('title', $agreement->title)) }">@csrf @if ($agreement->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="client_id">Client</label><select id="client_id" name="client_id" required x-model="clientId" class="input"><option value="">Choose a client</option>@foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>@error('client_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="project_id">Project (optional)</label><select id="project_id" name="project_id" class="input"><option value="">None</option><template x-for="p in projects.filter(p => p.client_id === clientId)" :key="p.id"><option :value="p.id" x-text="p.name" :selected="p.id === '{{ old('project_id', $agreement->project_id) }}'"></option></template></select></div>
    </div>
    @unless ($agreement->exists)
    <div><label class="label" for="tpl">Start from a template</label><select id="tpl" class="input" @change="if ($event.target.value) { title = $event.target.value; body = templates[$event.target.value]; }"><option value="">Blank</option>@foreach ($templates as $name => $text)<option>{{ $name }}</option>@endforeach</select></div>
    @endunless
    <div><label class="label" for="title">Title</label><input id="title" name="title" required maxlength="160" x-model="title" class="input">@error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
    <div><label class="label" for="body">Text</label><textarea id="body" name="body" rows="16" required maxlength="60000" x-model="body" class="input font-mono text-sm"></textarea>@error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
    <div class="flex gap-2"><button class="btn-primary">Save draft</button><a href="{{ route('agreements.index') }}" class="btn-secondary">Cancel</a></div>
</form>
@endsection
