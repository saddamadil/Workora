@extends('layouts.app')
@section('title', 'Our tasks')
@section('content')
<x-page-title title="Our tasks" sub="What is being worked on across your projects." />
@forelse ($tasks as $projectId => $list)
    <section class="card mb-5" aria-labelledby="p-{{ $projectId }}">
        <div class="border-b border-slate-100 px-5 py-3"><h2 id="p-{{ $projectId }}" class="font-semibold text-slate-900"><a class="hover:underline" href="{{ route('portal.project', [$projects[$projectId]->slug, 'tab' => 'tasks']) }}">{{ $projects[$projectId]->name }}</a></h2></div>
        @foreach ($list as $t)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="min-w-0 flex-1 text-slate-900">{{ $t->title }}</span>@if ($t->due_at)<span class="text-slate-500">Due {{ $t->due_at->format('d M') }}</span>@endif<span class="rounded-full {{ \App\Models\Task::STATUS_STYLES[$t->status] ?? '' }} px-2.5 py-0.5 text-xs font-medium">{{ \App\Models\Task::STATUS_LABELS[$t->status] ?? $t->status }}</span></div>
        @endforeach
    </section>
@empty
    <div class="card px-6 py-12 text-center text-sm text-slate-500">No tasks yet. They appear here as your freelancer plans the work.</div>
@endforelse
@endsection
