@extends('layouts.app')
@section('title', $req->title)
@section('content')
@php $tone = ['new' => 'blue', 'discussing' => 'amber', 'accepted' => 'green', 'in_progress' => 'indigo', 'completed' => 'green', 'declined' => 'slate']; $steps = ['new', 'discussing', 'accepted', 'in_progress', 'completed']; @endphp
<div class="mb-2 text-sm"><a href="{{ route('portal.requests.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Requests</a></div>
<x-page-title :title="$req->title" :sub="'Sent '.$req->created_at->format('d M Y')"><x-pill :tone="$tone[$req->status] ?? 'slate'">{{ \App\Models\ClientRequest::STATUSES[$req->status] }}</x-pill></x-page-title>
<ol class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Progress">
    @foreach ($steps as $s)@php $reached = $req->status !== 'declined' && array_search($req->status, $steps) >= array_search($s, $steps); @endphp<li class="rounded-full border px-3 py-1 {{ $reached ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 text-slate-500' }}">{{ $reached ? '✓ ' : '' }}{{ \App\Models\ClientRequest::STATUSES[$s] }}</li>@endforeach
    @if ($req->status === 'declined')<li class="rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-slate-700">Declined</li>@endif
</ol>
<div class="card max-w-2xl space-y-4 p-6 text-sm">
    <p class="whitespace-pre-line text-slate-800">{{ $req->description ?: 'No details given.' }}</p>
    <dl class="grid grid-cols-2 gap-3"><div><dt class="text-xs text-slate-500">Priority</dt><dd class="font-medium">{{ ucfirst($req->priority) }}</dd></div><div><dt class="text-xs text-slate-500">Project</dt><dd class="font-medium">{{ $req->project?->name ?: '—' }}</dd></div><div><dt class="text-xs text-slate-500">Preferred deadline</dt><dd class="font-medium">{{ $req->preferred_deadline?->format('d M Y') ?: '—' }}</dd></div></dl>
    @foreach ($req->files as $f)<a href="{{ route('portal.files.show', $f) }}" target="_blank" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50"><i class="bi {{ $f->icon()[0] }}"></i>{{ $f->original_name }}</a>@endforeach
    @if ($req->response_note)<div class="rounded-lg border-l-4 border-brand-500 bg-slate-50 p-4"><div class="mb-1 text-xs font-semibold uppercase text-slate-500">Reply from your freelancer</div>{{ $req->response_note }}</div>@endif
</div>
@endsection
