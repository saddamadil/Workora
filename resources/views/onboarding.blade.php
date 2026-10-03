@extends('layouts.guest')
@section('title', 'Set up your workspace · Workora')
@section('content')
<div class="card p-7">
    <h1 class="text-xl font-bold text-slate-900">One last step</h1>
    <p class="mt-1 text-sm text-slate-500">You are not part of a workspace yet. Name one to keep your files in.</p>
    <form method="POST" action="{{ route('onboarding.store') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label for="workspace" class="label">Workspace name</label>
            <input id="workspace" name="workspace" value="{{ old('workspace', $suggested) }}" class="input" required autofocus>
        </div>
        <button class="btn-primary w-full">Create workspace</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf
        <button class="text-sm text-slate-500 hover:underline">Sign out</button>
    </form>
</div>
@endsection
