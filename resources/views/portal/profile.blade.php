@extends('layouts.app')
@section('title', 'Profile')
@section('content')
<x-page-title title="Profile" sub="Your name and sign-in details." />
<div class="grid max-w-3xl gap-6">
    <form method="POST" action="{{ route('portal.profile.update') }}" class="card space-y-4 p-6">@csrf
        <h2 class="font-semibold text-slate-900">About you</h2>
        <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><span class="label">Email</span><p class="text-sm text-slate-700">{{ $user->email }}</p></div>
        <div><span class="label">Company</span><p class="text-sm text-slate-700">{{ $client->name }}</p></div>
        <button class="btn-primary">Save</button>
    </form>
    <form method="POST" action="{{ route('profile.password') }}" class="card space-y-4 p-6">@csrf
        <h2 class="font-semibold text-slate-900">Change password</h2>
        <div><label class="label" for="current_password">Current password</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="input">@error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="password">New password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="input">@error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="password_confirmation">Confirm</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input"></div>
        </div>
        <button class="btn-secondary">Change password</button>
    </form>
</div>
@endsection
