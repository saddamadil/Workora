@extends('layouts.guest')
@section('title', 'Reset password · Freelancy')
@section('content')
<div class="card p-7">
    <h1 class="text-xl font-bold text-slate-900">Reset your password</h1>
    <p class="mt-1 text-sm text-slate-500">Enter your email and we will send you a link.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">@csrf
        <div><label for="email" class="label">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="input" required autofocus autocomplete="email">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <button class="btn-primary w-full">Send reset link</button>
    </form>
</div>
<p class="mt-5 text-center text-sm text-slate-500"><a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">Back to sign in</a></p>
@endsection
