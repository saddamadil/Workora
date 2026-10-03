@extends('layouts.app')
@section('title', $role->isFreelancer() ? 'My tasks' : 'Tasks')
@section('content')
@php $q = fn (array $x) => route('tasks.index', array_filter(array_merge(request()->only('q', 'scope', 'mine', 'project'), $x), fn ($v) => $v !== null && $v !== '')); @endphp
<x-page-title :title="$role->isFreelancer() ? 'My tasks' : 'Tasks'" sub="Everything you can see across your projects." />

<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center">
    <div class="flex flex-wrap gap-2">
        <a href="{{ $q(['scope' => null]) }}" class="chip {{ $scope === 'open' ? 'chip-active' : '' }}">Open</a>
        <a href="{{ $q(['scope' => 'review']) }}" class="chip {{ $scope === 'review' ? 'chip-active' : '' }}">In review @if ($reviewCount)<span class="rounded-full bg-amber-100 px-1.5 text-xs text-amber-800">{{ $reviewCount }}</span>@endif</a>
        <a href="{{ $q(['scope' => 'done']) }}" class="chip {{ $scope === 'done' ? 'chip-active' : '' }}">Approved</a>
        @unless ($role->isFreelancer())<a href="{{ $q(['mine' => $mine ? null : 1]) }}" class="chip {{ $mine ? 'chip-active' : '' }}"><i class="bi bi-person"></i> Assigned to me</a>@endunless
    </div>
    <form method="GET" class="relative lg:ms-auto lg:w-72">
        @foreach (request()->only('scope', 'mine', 'project') as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <i class="bi bi-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="search" name="q" value="{{ $search }}" placeholder="Search tasks" class="input pl-10" aria-label="Search tasks">
    </form>
</div>

<div class="card">
    @include('tasks._list', ['tasks' => $tasks, 'empty' => 'No tasks match.'])
</div>
<div class="mt-6">{{ $tasks->links() }}</div>
@endsection
