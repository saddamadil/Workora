@extends('layouts.guest')
@section('title', 'Sign in · Workora')
@section('content')
<div class="card p-7">
    <h1 class="text-xl font-bold text-slate-900">Welcome back</h1>
    <p class="mt-1 text-sm text-slate-500">Sign in to see your files.</p>

    <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required autofocus autocomplete="email">
        </div>
        <div>
            <label for="password" class="label">Password</label>
            <input id="password" name="password" type="password" class="input" required autocomplete="current-password">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" class="rounded border-slate-300"> Keep me signed in</label>
        <button class="btn-primary w-full">Sign in</button>
    </form>
</div>
<p class="mt-5 text-center text-sm text-slate-500">New here? <a href="{{ route('register') }}" class="font-semibold text-brand-600 hover:underline">Create a free account</a></p>
@endsection
