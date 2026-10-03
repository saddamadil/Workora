@extends('layouts.guest')
@section('title', 'Welcome · Workora')
@section('width', 'max-w-lg')
@section('content')
<div class="card p-7">
    <h1 class="text-xl font-bold text-slate-900">Welcome, {{ auth()->user()->name }}</h1>
    <p class="mt-1 text-sm text-slate-500">You are not part of a company yet.</p>

    <div class="mt-5 rounded-xl bg-brand-50 p-4 text-sm text-slate-700">
        <div class="font-semibold text-slate-900"><i class="bi bi-envelope-open me-1"></i> Waiting for an invitation?</div>
        <p class="mt-1">Companies invite freelancers by email. Open the link in your invitation while signed in with <strong>{{ auth()->user()->email }}</strong>, or paste it here:</p>
        <form x-data="{ link: '' }" @submit.prevent="window.location = link" class="mt-3 flex gap-2">
            <input x-model="link" class="input" placeholder="https://…/invite/…" aria-label="Invitation link">
            <button class="btn-primary">Open</button>
        </form>
    </div>

    <div class="my-5 flex items-center gap-3 text-xs uppercase tracking-wide text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>or<span class="h-px flex-1 bg-slate-200"></span></div>

    <form method="POST" action="{{ route('onboarding.store') }}" class="space-y-3">
        @csrf
        <label for="workspace" class="label">Create your own company</label>
        <input id="workspace" name="workspace" value="{{ old('workspace', $suggested) }}" class="input" required>
        <button class="btn-secondary w-full">Create company</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf
        <button class="text-sm text-slate-500 hover:underline">Sign out</button>
    </form>
</div>
@endsection
