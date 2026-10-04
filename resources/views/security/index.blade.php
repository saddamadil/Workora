@extends('layouts.app')
@section('title', 'Security')
@section('content')
<x-page-title title="Security" sub="Protect your account with a second step at sign-in, and see where you are signed in." />
<div class="grid max-w-3xl gap-6">
    @if ($codes)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-5"><h2 class="font-semibold text-amber-900">Your recovery codes</h2>
            <p class="mt-1 text-sm text-amber-900">Each code works once if you lose your phone. Save them somewhere safe. They are shown only now.</p>
            <div class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">@foreach ($codes as $c)<span class="rounded bg-white px-2 py-1">{{ $c }}</span>@endforeach</div></div>
    @endif

    <section class="card p-5" aria-labelledby="tf">
        <div class="flex items-start justify-between gap-3"><div><h2 id="tf" class="font-semibold text-slate-900">Two-factor sign-in</h2><p class="text-sm text-slate-500">Use an authenticator app such as Google Authenticator or Authy.</p></div>
            <x-pill :tone="$enabled ? 'green' : 'slate'">{{ $enabled ? 'On' : 'Off' }}</x-pill></div>
        @if ($enabled)
            <form method="POST" action="{{ route('security.codes') }}" class="mt-4 flex flex-wrap items-end gap-2">@csrf
                <div class="min-w-48 flex-1"><label class="label" for="pw1">Password</label><input id="pw1" name="password" type="password" required class="input" autocomplete="current-password"></div><button class="btn-secondary">New recovery codes</button></form>
            <form method="POST" action="{{ route('security.disable') }}" class="mt-3 flex flex-wrap items-end gap-2" onsubmit="return confirm('Turn off two-factor sign-in?')">@csrf
                <div class="min-w-48 flex-1"><label class="label" for="pw2">Password</label><input id="pw2" name="password" type="password" required class="input" autocomplete="current-password"></div><button class="btn-secondary text-red-600">Turn off</button></form>
            @error('password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        @elseif ($setupSecret)
            <div class="mt-4 grid gap-4 sm:grid-cols-[auto_1fr]"><img src="{{ $qr }}" alt="QR code for your authenticator app" class="size-40 rounded border border-slate-200">
                <div class="space-y-3 text-sm"><p>Scan the code with your app, or type this key: <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono">{{ $setupSecret }}</code></p>
                    <form method="POST" action="{{ route('security.confirm') }}" class="flex flex-wrap items-end gap-2">@csrf<div><label class="label" for="code">6-digit code</label><input id="code" name="code" class="input w-40" inputmode="numeric" autocomplete="one-time-code" required></div><button class="btn-primary">Turn on</button></form>
                    @error('code')<p class="text-red-600">{{ $message }}</p>@enderror</div></div>
        @else
            <form method="POST" action="{{ route('security.start') }}" class="mt-4">@csrf<button class="btn-primary">Set up two-factor sign-in</button></form>
        @endif
    </section>

    <section class="card p-5" aria-labelledby="ss">
        <h2 id="ss" class="font-semibold text-slate-900">Where you are signed in</h2>
        @if (! $tracked)<p class="mt-2 text-sm text-slate-500">This site does not store sessions in the database, so devices cannot be listed.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100 text-sm">@foreach ($sessions as $s)<li class="flex flex-wrap items-center justify-between gap-2 py-2"><span>{{ $s['agent'] }} <span class="text-slate-500">· {{ $s['ip'] }}</span>@if ($s['current']) <x-pill tone="green">This device</x-pill>@endif</span><span class="text-xs text-slate-500">{{ $s['when']->diffForHumans() }}</span></li>@endforeach</ul>
            <form method="POST" action="{{ route('security.sign-out-others') }}" class="mt-4 flex flex-wrap items-end gap-2">@csrf
                <div class="min-w-48 flex-1"><label class="label" for="pw3">Password</label><input id="pw3" name="password" type="password" required class="input" autocomplete="current-password"></div><button class="btn-secondary">Sign out other devices</button></form>
        @endif
    </section>
</div>
@endsection
