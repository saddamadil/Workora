@extends('layouts.guest')
@section('title', 'Password needed · Workora')
@section('content')
<div class="card p-7 text-center">
    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-50 text-3xl text-brand-600"><i class="bi bi-lock"></i></span>
    <h1 class="mt-4 text-lg font-bold text-slate-900">This file is protected</h1>
    <p class="mt-1 text-sm text-slate-500">Enter the password you were given to open <strong class="break-words">{{ $file->original_name }}</strong>.</p>
    <form method="POST" action="{{ route('share.unlock', $link->token) }}" class="mt-5 space-y-3 text-left">
        @csrf
        <label for="password" class="sr-only">Password</label>
        <input id="password" type="password" name="password" class="input" placeholder="Password" required autofocus>
        <button class="btn-primary w-full">Unlock</button>
    </form>
</div>
@endsection
