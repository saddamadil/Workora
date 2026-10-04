@extends('layouts.guest')
@section('title', 'New password · Freelancy')
@section('content')
<div class="card p-7">
    <h1 class="text-xl font-bold text-slate-900">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div><label for="email" class="label">Email</label><input id="email" name="email" type="email" value="{{ old('email', $email) }}" class="input" required autocomplete="email">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="password" class="label">New password</label><input id="password" name="password" type="password" class="input" required minlength="8" autocomplete="new-password">@error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="password_confirmation" class="label">Confirm</label><input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password"></div>
        <button class="btn-primary w-full">Change password</button>
    </form>
</div>
@endsection
