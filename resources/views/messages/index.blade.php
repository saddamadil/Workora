@extends('layouts.app')
@section('title', 'Messages')
@section('content')
@php
    $p = $portal ? 'portal.' : '';
    $q = fn (array $extra = []) => array_filter(($portal ? [] : ['client' => $client?->id]) + $extra);
@endphp
<x-page-title title="Messages" sub="Every project has its own conversation, so nothing gets mixed up." />

@if (! $client)
    <div class="card px-6 py-12 text-center"><i class="bi bi-chat-dots text-4xl text-slate-300"></i>
        <p class="mt-3 font-semibold text-slate-700">No conversations yet.</p>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Add a client first, then message them here.</p>
        @can('manage-clients')<a href="{{ route('clients.create') }}" class="btn-primary mt-4"><i class="bi bi-plus-lg"></i> Add client</a>@endcan</div>
@else
<div class="grid gap-4 lg:grid-cols-[18rem_1fr]" x-data="{ reply: null, toTask: null }">
    <aside class="space-y-3" aria-label="Conversations">
        @unless ($portal)
            <div class="card p-2">
                @foreach ($clients as $c)
                    <a href="{{ route('messages.index', ['client' => $c->id]) }}" class="flex min-h-11 items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm {{ $c->id === $client->id ? 'bg-slate-100 font-semibold text-slate-900' : 'text-slate-600 hover:bg-slate-50' }}">
                        <span class="truncate">{{ $c->name }}</span>
                        @if (($unreadByClient[$c->id] ?? 0) > 0)<span class="rounded-full bg-brand-500 px-2 text-xs font-semibold text-slate-900">{{ $unreadByClient[$c->id] }}</span>@endif
                    </a>
                @endforeach
            </div>
        @endunless
        <div class="card p-2">
            @foreach ($threads as $t)
                <a href="{{ route($p.'messages.index', $q(['project' => $t['key'] === 'general' ? null : $t['key']])) }}" @if (($project?->id ?? 'general') === $t['key']) aria-current="true" @endif
                   class="flex min-h-11 items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm {{ ($project?->id ?? 'general') === $t['key'] ? 'bg-slate-100 font-semibold text-slate-900' : 'text-slate-600 hover:bg-slate-50' }}">
                    <span class="truncate"><i class="bi {{ $t['key'] === 'general' ? 'bi-chat-left-text' : 'bi-kanban' }} me-1.5"></i>{{ $t['title'] }}</span>
                    @if ($t['unread'])<span class="rounded-full bg-brand-500 px-2 text-xs font-semibold text-slate-900">{{ $t['unread'] }}<span class="sr-only"> unread</span></span>@endif
                </a>
            @endforeach
        </div>
    </aside>

    <section class="card flex min-h-[32rem] flex-col"
             x-data="{ last: '{{ $messages->last()?->id }}', count: {{ $messages->count() }}, typing: [], online: false, lastTyped: 0 }"
             x-init="
                const box = $refs.box; box.scrollTop = box.scrollHeight;
                setInterval(async () => {
                    if (document.hidden) return;
                    const r = await fetch(@js(route($p.'messages.poll', $q(['project' => $project?->id, 'q' => $search]))), { headers: { 'Accept': 'application/json' } });
                    if (!r.ok) return; const d = await r.json();
                    typing = d.typing; online = d.online;
                    if (d.last !== last || d.count !== count) { const atEnd = box.scrollHeight - box.scrollTop - box.clientHeight < 80; $refs.list.innerHTML = d.html; last = d.last; count = d.count; if (atEnd) box.scrollTop = box.scrollHeight; }
                }, 5000);
             ">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
            <div class="min-w-0"><h2 class="truncate font-semibold text-slate-900">{{ $client->name }}@if ($project) <span class="font-normal text-slate-500">/ {{ $project->name }}</span>@endif</h2>
                <p class="text-xs text-slate-500"><span class="inline-block size-2 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-slate-300'"></span> <span x-text="online ? 'Online now' : 'Offline'"></span><span x-show="typing.length" x-text="' · ' + typing.join(', ') + ' typing...'"></span></p></div>
            <form method="GET" action="{{ route($p.'messages.index') }}" class="flex gap-2" role="search">
                @foreach ($q(['project' => $project?->id]) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <input type="search" name="q" value="{{ $search }}" placeholder="Search messages" class="input w-44" aria-label="Search messages"><button class="btn-secondary" aria-label="Search"><i class="bi bi-search"></i></button>
            </form>
        </header>

        <div x-ref="box" class="flex-1 space-y-4 overflow-y-auto px-5 py-4" style="max-height: 60vh" aria-live="polite"><div x-ref="list" class="space-y-4">@include('messages._list', ['messages' => $messages, 'portal' => $portal])</div></div>

        <form method="POST" action="{{ route($p.'messages.store') }}" enctype="multipart/form-data" class="border-t border-slate-100 p-4" x-data="{ names: [] }">
            @csrf
            @unless ($portal)<input type="hidden" name="client_id" value="{{ $client->id }}">@endunless
            <input type="hidden" name="project_id" value="{{ $project?->id }}">
            <input type="hidden" name="parent_id" :value="reply ? reply.id : ''">
            <template x-if="reply"><div class="mb-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 text-xs text-slate-600"><span>Replying to: <span x-text="reply.text"></span></span><button type="button" @click="reply = null" aria-label="Cancel reply"><i class="bi bi-x-lg"></i></button></div></template>
            @error('body')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror
            <label for="body" class="sr-only">Message</label>
            <textarea id="body" name="body" x-ref="body" rows="2" class="input" placeholder="Write a message. Use @name to mention someone." maxlength="5000"
                      @input="if (Date.now() - lastTyped > 3000) { lastTyped = Date.now(); fetch(@js(route($p.'messages.typing')), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ client_id: @js($client->id), project_id: @js($project?->id) }) }) }"
                      @keydown.ctrl.enter="$el.form.requestSubmit()" @keydown.meta.enter="$el.form.requestSubmit()"></textarea>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-1.5 rounded-lg px-2 hover:bg-slate-50"><i class="bi bi-paperclip text-lg"></i> Attach<input type="file" name="files[]" multiple class="sr-only" @change="names = [...$event.target.files].map(f => f.name)"></label>
                    <span class="max-w-xs truncate text-xs text-slate-500" x-text="names.join(', ')"></span>
                    <label class="inline-flex items-center gap-1.5"><input type="checkbox" name="is_important" value="1" class="rounded border-slate-300"> Important</label>
                </div>
                <button class="btn-primary"><i class="bi bi-send"></i> Send</button>
            </div>
        </form>

        {{-- Create a task (staff) or a request (client) from a message. --}}
        <div x-show="toTask" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4" @keydown.escape.window="toTask = null" role="dialog" aria-modal="true">
            <form method="POST" :action="'{{ url($portal ? '/portal/messages' : '/messages') }}/' + (toTask ? toTask.id : '') + '{{ $portal ? '/request' : '/task' }}'" @click.outside="toTask = null" class="card w-full max-w-md space-y-3 p-6">
                @csrf
                <h2 class="text-lg font-bold text-slate-900">{{ $portal ? 'Make this a work request' : 'Create a task from this message' }}</h2>
                <div><label class="label" for="tt-title">Title</label><input id="tt-title" name="title" class="input" required maxlength="200" :value="toTask ? toTask.title : ''"></div>
                @unless ($portal)@unless ($project)<div><label class="label" for="tt-project">Project</label><select id="tt-project" name="project_id" class="input" required><option value="">Choose</option>@foreach ($projects as $pr)<option value="{{ $pr->id }}">{{ $pr->name }}</option>@endforeach</select></div>@endunless @endunless
                <p class="text-xs text-slate-500">The original message becomes the description.</p>
                <div class="flex justify-end gap-2"><button type="button" class="btn-secondary" @click="toTask = null">Cancel</button><button class="btn-primary">Create</button></div>
            </form>
        </div>
    </section>
</div>
@endif
@endsection
