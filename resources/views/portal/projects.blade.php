@extends('layouts.app')
@section('title', 'Our projects')
@section('content')
<x-page-title title="Our projects" sub="Everything you are working on together." />
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($projects as $p) @include('portal._project-card', ['p' => $p])
    @empty
        <div class="card px-6 py-12 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-3">No projects yet. They will appear here as soon as your freelancer creates one.</div>
    @endforelse
</div>
@endsection
