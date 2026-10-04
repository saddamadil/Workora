@extends('layouts.guest')
@section('title', 'Link unavailable · Freelancy')
@section('content')
<div class="card p-8 text-center">
    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-slate-100 text-3xl text-slate-500"><i class="bi bi-slash-circle"></i></span>
    <h1 class="mt-4 text-lg font-bold text-slate-900">This link no longer works</h1>
    <p class="mt-1 text-sm text-slate-500">{{ $reason }} Ask the person who sent it to share it again.</p>
</div>
@endsection
