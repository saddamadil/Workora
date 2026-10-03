@extends('layouts.guest')
@section('title', 'Invitation · Workora')
@section('content')
<div class="card p-7 text-center">
    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-50 text-3xl text-brand-600"><i class="bi bi-envelope-open"></i></span>
    @if (! $usable)
        <h1 class="mt-4 text-lg font-bold text-slate-900">This invitation is no longer valid</h1>
        <p class="mt-1 text-sm text-slate-500">It was already used or has expired. Ask {{ $organization->name }} to send a new one.</p>
    @else
        <h1 class="mt-4 text-lg font-bold text-slate-900">Join {{ $organization->name }}</h1>
        <p class="mt-1 text-sm text-slate-500">
            You were invited as {{ $invitation->member_type === 'freelancer' ? 'a freelancer' : 'a '.str_replace('_', ' ', $invitation->role) }}.
            Invitation for <strong>{{ $invitation->email }}</strong>.
        </p>
        @auth
            @if ($emailMatches)
                <form method="POST" action="{{ route('invite.accept', $invitation->token) }}" class="mt-6">@csrf
                    <button class="btn-primary w-full">Accept and join</button>
                </form>
            @else
                <p class="mt-5 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">You are signed in as {{ auth()->user()->email }}. Sign out and use <strong>{{ $invitation->email }}</strong> to accept this invitation.</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="btn-secondary w-full">Sign out</button></form>
            @endif
        @else
            <div class="mt-6 grid gap-3">
                <a href="{{ route('register', ['as' => $invitation->member_type === 'freelancer' ? 'freelancer' : 'company', 'email' => $invitation->email]) }}" class="btn-primary">Create an account</a>
                <a href="{{ route('login') }}" class="btn-secondary">I already have an account</a>
            </div>
        @endauth
    @endif
</div>
@endsection
