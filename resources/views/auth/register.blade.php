@extends('layouts.guest')
@section('title', 'Create account')
@section('content')
@php $type = old('account_type', request('as', 'freelancer')); @endphp
<div class="card p-7" x-data="{ type: '{{ $type }}' }">
    <h1 class="text-xl font-bold text-slate-900">Create your account</h1>
    <p class="mt-1 text-sm text-slate-500">Free to start. Pick what describes you.</p>

    <form method="POST" action="{{ url('/register') }}" class="mt-6 space-y-4">
        @csrf
        <div class="grid grid-cols-2 gap-3" role="radiogroup" aria-label="Account type">
            <label :class="type === 'freelancer' ? 'border-brand-600 bg-brand-50 ring-2 ring-brand-100' : 'border-slate-200 hover:bg-slate-50'" class="cursor-pointer rounded-xl border p-3 text-center transition">
                <input type="radio" name="account_type" value="freelancer" x-model="type" class="sr-only">
                <i class="bi bi-person-workspace text-2xl text-brand-600"></i>
                <span class="mt-1 block text-sm font-semibold text-slate-900">I'm a freelancer</span>
                <span class="block text-xs text-slate-500">Run my clients and projects</span>
            </label>
            <label :class="type === 'company' ? 'border-brand-600 bg-brand-50 ring-2 ring-brand-100' : 'border-slate-200 hover:bg-slate-50'" class="cursor-pointer rounded-xl border p-3 text-center transition">
                <input type="radio" name="account_type" value="company" x-model="type" class="sr-only">
                <i class="bi bi-building text-2xl text-brand-600"></i>
                <span class="mt-1 block text-sm font-semibold text-slate-900">I run a company</span>
                <span class="block text-xs text-slate-500">Manage a team of freelancers</span>
            </label>
        </div>
        <div>
            <label for="name" class="label">Your name</label>
            <input id="name" name="name" value="{{ old('name') }}" class="input" required autofocus autocomplete="name">
        </div>
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', request('email')) }}" class="input" required autocomplete="email">
        </div>
        <div x-show="!{{ session()->has('invite_token') ? 'true' : 'false' }}">
            <label for="workspace" class="label"><span x-text="type === 'company' ? 'Company name' : 'Business name'"></span> <span class="font-normal text-slate-400" x-show="type === 'freelancer'">(optional)</span></label>
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
