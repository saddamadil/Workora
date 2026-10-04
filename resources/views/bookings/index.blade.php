@extends('layouts.app')
@section('title', 'Bookings')
@section('content')
<x-page-title title="Bookings and time off" sub="A public page where clients and prospects pick a free time with you." />
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        @if ($page)
            <div class="card flex flex-wrap items-center justify-between gap-3 p-4 text-sm"><span class="min-w-0"><span class="text-slate-500">Your public link</span><span class="block truncate font-semibold text-slate-900">{{ $page->url() }}</span></span>
                <button type="button" class="btn-secondary btn-sm" x-data @click="navigator.clipboard.writeText('{{ $page->url() }}')"><i class="bi bi-clipboard"></i> Copy</button></div>
        @endif
        <section class="card"><div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Upcoming meetings</h2></div>
            @forelse ($upcoming as $b)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
                    <span><strong class="text-slate-900">{{ $b->name }}</strong> <span class="text-slate-500">{{ $b->email }}</span><span class="block text-slate-600">{{ $b->starts_at->copy()->setTimezone($page->timezone)->format('D d M Y, H:i') }} ({{ $page->timezone }})@if ($b->notes) · {{ $b->notes }}@endif</span></span>
                    <form method="POST" action="{{ route('bookings.cancel', $b) }}" onsubmit="return confirm('Cancel this meeting and email the guest?')">@csrf<button class="text-xs text-red-600 hover:underline">Cancel</button></form></div>
            @empty<p class="px-5 py-8 text-center text-sm text-slate-500">No upcoming meetings.</p>@endforelse
        </section>
        @if ($past->isNotEmpty())<section class="card"><div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Earlier</h2></div>
            @foreach ($past as $b)<div class="flex justify-between border-b border-slate-100 px-5 py-2.5 text-sm text-slate-600 last:border-0"><span>{{ $b->name }} · {{ $b->starts_at->format('d M Y, H:i') }} UTC</span>@if ($b->status === 'cancelled')<x-pill tone="slate">Cancelled</x-pill>@endif</div>@endforeach</section>@endif
        <section class="card"><div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Time off</h2><p class="text-sm text-slate-500">Whole days nobody can book you.</p></div>
            @foreach ($timeOff as $t)<div class="flex items-center justify-between border-b border-slate-100 px-5 py-2.5 text-sm"><span>{{ $kinds[$t->kind] ?? $t->kind }} · {{ $t->starts_on->format('d M Y') }}@if (! $t->starts_on->eq($t->ends_on)) to {{ $t->ends_on->format('d M Y') }}@endif @if ($t->note)<span class="text-slate-500">· {{ $t->note }}</span>@endif</span>
                <form method="POST" action="{{ route('timeoff.destroy', $t) }}">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Remove</button></form></div>@endforeach
            <form method="POST" action="{{ route('timeoff.store') }}" class="grid gap-3 p-5 sm:grid-cols-5">@csrf
                <div><label class="label" for="to-s">From</label><input id="to-s" type="date" name="starts_on" required class="input"></div>
                <div><label class="label" for="to-e">To</label><input id="to-e" type="date" name="ends_on" required class="input">@error('ends_on')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="to-k">Type</label><select id="to-k" name="kind" class="input">@foreach ($kinds as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div><label class="label" for="to-n">Note</label><input id="to-n" name="note" maxlength="200" class="input"></div>
                <div class="flex items-end"><button class="btn-secondary w-full">Add</button></div></form>
        </section>
    </div>
    <form method="POST" action="{{ route('bookings.save') }}" class="card h-fit space-y-3 p-5">@csrf
        <h2 class="font-semibold text-slate-900">Booking page</h2>
        <div><label class="label" for="bp-s">Address</label><div class="flex items-center gap-1 text-sm text-slate-500"><span class="shrink-0">/book/</span><input id="bp-s" name="slug" required minlength="3" maxlength="60" value="{{ old('slug', $page?->slug) }}" class="input"></div>@error('slug')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="bp-t">Title</label><input id="bp-t" name="title" required maxlength="120" value="{{ old('title', $page?->title ?? 'Intro call') }}" class="input"></div>
        <div><label class="label" for="bp-d">Description</label><textarea id="bp-d" name="description" rows="2" maxlength="1000" class="input">{{ old('description', $page?->description) }}</textarea></div>
        <div><label class="label" for="bp-l">Where (call link or address)</label><input id="bp-l" name="location" maxlength="200" value="{{ old('location', $page?->location) }}" class="input"></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="label" for="bp-m">Length</label><select id="bp-m" name="duration_minutes" class="input">@foreach ([15, 20, 30, 45, 60, 90, 120] as $m)<option value="{{ $m }}" @selected(old('duration_minutes', $page?->duration_minutes ?? 30) == $m)>{{ $m }} min</option>@endforeach</select></div>
            <div><label class="label" for="bp-b">Gap after</label><input id="bp-b" type="number" name="buffer_minutes" min="0" max="60" value="{{ old('buffer_minutes', $page?->buffer_minutes ?? 0) }}" class="input"></div>
            <div><label class="label" for="bp-n">Notice (hours)</label><input id="bp-n" type="number" name="notice_hours" min="0" max="336" value="{{ old('notice_hours', $page?->notice_hours ?? 12) }}" class="input"></div>
            <div><label class="label" for="bp-w">Days ahead</label><input id="bp-w" type="number" name="window_days" min="1" max="180" value="{{ old('window_days', $page?->window_days ?? 30) }}" class="input"></div></div>
        <div><label class="label" for="bp-z">Time zone</label><select id="bp-z" name="timezone" class="input">@foreach ($zones as $z)<option @selected(old('timezone', $page?->timezone ?? config('app.timezone')) === $z)>{{ $z }}</option>@endforeach</select></div>
        <fieldset class="space-y-1.5"><legend class="label">Available hours</legend>
            @foreach ($days as $n => $name)@php $h = $hours[(string) $n] ?? null; @endphp
                <div class="flex items-center gap-2 text-sm"><label class="flex w-28 items-center gap-2"><input type="checkbox" name="days[{{ $n }}][on]" value="1" @checked($h)> {{ $name }}</label>
                    <input type="time" name="days[{{ $n }}][from]" value="{{ $h[0] ?? '09:00' }}" class="input !py-1" aria-label="{{ $name }} from"><span>-</span><input type="time" name="days[{{ $n }}][to]" value="{{ $h[1] ?? '17:00' }}" class="input !py-1" aria-label="{{ $name }} to"></div>@endforeach</fieldset>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $page?->active ?? true))> Page is open for booking</label>
        <button class="btn-primary w-full">Save</button>
    </form>
</div>
@endsection
