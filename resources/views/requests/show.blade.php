@extends('layouts.app')
@section('title', $req->title)
@section('content')
@php $tone = ['new' => 'blue', 'discussing' => 'amber', 'accepted' => 'green', 'in_progress' => 'indigo', 'completed' => 'green', 'declined' => 'slate']; @endphp
<div class="mb-2 text-sm"><a href="{{ route('requests.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Requests</a></div>
<x-page-title :title="$req->title" :sub="$req->client->name.' · '.$req->created_at->diffForHumans()"><x-pill :tone="$tone[$req->status] ?? 'slate'">{{ \App\Models\ClientRequest::STATUSES[$req->status] }}</x-pill></x-page-title>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card space-y-4 p-6 text-sm lg:col-span-2">
        <p class="whitespace-pre-line text-slate-800">{{ $req->description ?: 'No details given.' }}</p>
        <dl class="grid grid-cols-3 gap-3"><div><dt class="text-xs text-slate-500">Priority</dt><dd class="font-medium">{{ ucfirst($req->priority) }}</dd></div><div><dt class="text-xs text-slate-500">Project</dt><dd class="font-medium">{{ $req->project?->name ?: '—' }}</dd></div><div><dt class="text-xs text-slate-500">Wanted by</dt><dd class="font-medium">{{ $req->preferred_deadline?->format('d M Y') ?: '—' }}</dd></div></dl>
        @foreach ($req->files as $f)<a href="{{ route('files.show', $f) }}" target="_blank" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50"><i class="bi {{ $f->icon()[0] }}"></i>{{ $f->original_name }}</a>@endforeach
        @if ($req->response_note)<div class="rounded-lg border-s-4 border-brand-500 bg-slate-50 p-4"><div class="mb-1 text-xs font-semibold uppercase text-slate-500">Your reply</div>{{ $req->response_note }}</div>@endif
        <a href="{{ route('messages.index', ['client' => $req->client_id, 'project' => $req->project_id]) }}" class="inline-flex items-center gap-1 text-brand-600 hover:underline"><i class="bi bi-chat-dots"></i> Discuss in messages</a>
    </div>
    <div class="space-y-4">
        <div class="card space-y-2 p-5">
            <h2 class="font-semibold text-slate-900">Answer this request</h2>
            <form method="POST" action="{{ route('requests.respond', $req) }}" class="space-y-2">@csrf
                <label class="sr-only" for="note">Note to the client</label><textarea id="note" name="response_note" rows="3" class="input" placeholder="Optional note to the client. Required if you decline."></textarea>
                <div class="grid grid-cols-2 gap-2">
                    <button name="status" value="accepted" class="btn-primary btn-sm">Accept</button>
                    <button name="status" value="discussing" class="btn-secondary btn-sm">Discuss</button>
                    <button name="status" value="in_progress" class="btn-secondary btn-sm">In progress</button>
                    <button name="status" value="completed" class="btn-secondary btn-sm">Completed</button>
                    <button name="status" value="declined" class="btn-secondary btn-sm col-span-2 text-red-600">Decline</button>
                </div>
            </form>
        </div>
        <div class="card space-y-3 p-5">
            <h2 class="font-semibold text-slate-900">Turn it into work</h2>
            <form method="POST" action="{{ route('requests.to-task', $req) }}" class="space-y-2">@csrf
                <label class="sr-only" for="proj">Project</label><select id="proj" name="project_id" class="input" required><option value="">Choose a project</option>@foreach ($projects as $p)<option value="{{ $p->id }}" @selected($req->project_id === $p->id)>{{ $p->name }}</option>@endforeach</select>
                <button class="btn-secondary w-full" @disabled($projects->isEmpty())><i class="bi bi-check2-square"></i> Convert to task</button>
            </form>
            <form method="POST" action="{{ route('requests.to-project', $req) }}">@csrf<button class="btn-secondary w-full"><i class="bi bi-kanban"></i> Convert to project</button></form>
        </div>
    </div>
</div>
@endsection
