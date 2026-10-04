@extends('layouts.guest')
@section('title', 'Two-factor sign-in · Freelancy')
@section('content')
<div class="card p-7">
    <h1 class="text-xl font-bold text-slate-900">Two-factor sign-in</h1>
    <p class="mt-1 text-sm text-slate-500">Open your authenticator app and enter the 6-digit code. Lost your phone? Use one of your recovery codes instead.</p>
    <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 space-y-4">@csrf
        <div><label for="code" class="label">Code</label><input id="code" name="code" class="input text-center text-lg tracking-widest" inputmode="numeric" autocomplete="one-time-code" required autofocus>@error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <button class="btn-primary w-full">Verify</button>
    </form>
</div>
<p class="mt-5 text-center text-sm text-slate-500"><a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">Back to sign in</a></p>
@endsection
