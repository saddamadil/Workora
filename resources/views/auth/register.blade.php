@extends('layouts.guest')
@section('title', 'Create account · Workora')
@section('content')
<div class="card p-7">
    <h1 class="text-xl font-bold text-slate-900">Create your account</h1>
    <p class="mt-1 text-sm text-slate-500">You get a private workspace for your files straight away.</p>

    <form method="POST" action="{{ url('/register') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="name" class="label">Your name</label>
            <input id="name" name="name" value="{{ old('name') }}" class="input" required autofocus autocomplete="name">
        </div>
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required autocomplete="email">
        </div>
        <div>
            <label for="workspace" class="label">Workspace name <span class="font-normal text-slate-400">(optional)</span></label>
            <input id="workspace" name="workspace" value="{{ old('workspace') }}" class="input" placeholder="e.g. Acme Studio">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" class="input" required minlength="8" autocomplete="new-password">
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirm</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
            </div>
        </div>
        <p class="text-xs text-slate-500">At least 8 characters.</p>
        <button class="btn-primary w-full">Create account</button>
    </form>
</div>
<p class="mt-5 text-center text-sm text-slate-500">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">Sign in</a></p>
@endsection
