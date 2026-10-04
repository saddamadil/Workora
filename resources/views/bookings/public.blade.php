@extends('layouts.guest')
@section('title', $page->title)
@section('width', 'max-w-2xl')
@section('content')
<div class="card p-6">
    <p class="text-sm text-slate-500">{{ $page->host->name }}</p>
    <h1 class="text-2xl font-bold text-slate-900">{{ $page->title }}</h1>
    <p class="mt-1 text-sm text-slate-600"><i class="bi bi-clock"></i> {{ $page->duration_minutes }} minutes @if ($page->location) · <i class="bi bi-geo-alt"></i> {{ $page->location }}@endif</p>
    @if ($page->description)<p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $page->description }}</p>@endif
    @if ($days->isEmpty())
        <p class="mt-6 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">There are no free times right now. Please check back later.</p>
    @else
        <h2 class="mt-6 text-sm font-semibold text-slate-900">Pick a day <span class="font-normal text-slate-500">(times in {{ $page->timezone }})</span></h2>
        <div class="mt-2 flex flex-wrap gap-2">@foreach ($days as $d)<a href="{{ route('book.show', [$page->slug, 'date' => $d->format('Y-m-d')]) }}" class="chip {{ $date === $d->format('Y-m-d') ? 'chip-active' : '' }}">{{ $d->format('D d M') }}</a>@endforeach</div>
        @if ($date)
        <form method="POST" action="{{ route('book.store', $page->slug) }}" class="mt-5 space-y-4" x-data="{ start: '{{ old('start') }}' }">@csrf
            <div class="flex flex-wrap gap-2">@foreach ($slots as $s)<label class="chip cursor-pointer" :class="start === '{{ $s->copy()->utc()->toIso8601String() }}' && 'chip-active'"><input type="radio" name="start" value="{{ $s->copy()->utc()->toIso8601String() }}" x-model="start" class="sr-only" required>{{ $s->format('H:i') }}</label>@endforeach</div>
            @error('start')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="grid gap-3 sm:grid-cols-2"><div><label class="label" for="bk-n">Your name</label><input id="bk-n" name="name" required maxlength="120" value="{{ old('name') }}" class="input">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="bk-e">Email</label><input id="bk-e" type="email" name="email" required maxlength="190" value="{{ old('email') }}" class="input">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div></div>
            <div><label class="label" for="bk-o">Anything we should know? (optional)</label><textarea id="bk-o" name="notes" rows="2" maxlength="1000" class="input">{{ old('notes') }}</textarea></div>
            <button class="btn-primary w-full" :disabled="!start">Book this time</button>
        </form>
        @endif
    @endif
</div>
@endsection
