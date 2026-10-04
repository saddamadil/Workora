@extends('layouts.app')
@section('title', 'Calendar')
@section('content')
@php
    $p = $portal ? 'portal.' : '';
    $kinds = ['task' => ['bi-check2-square', 'bg-sky-50 text-sky-800'], 'project' => ['bi-flag', 'bg-slate-100 text-slate-800'], 'milestone' => ['bi-signpost', 'bg-emerald-50 text-emerald-800'], 'invoice' => ['bi-receipt', 'bg-amber-50 text-amber-800'], 'meeting' => ['bi-camera-video', 'bg-brand-50 text-brand-700'], 'reminder' => ['bi-bell', 'bg-violet-50 text-violet-800']];
    $nav = fn ($d, $v = null) => route($p.'calendar.index', ['view' => $v ?? $view, 'date' => $d->toDateString()]);
    $chip = function ($e) use ($kinds) { return $kinds[$e['kind']] ?? $kinds['task']; };
@endphp
<x-page-title title="Calendar" sub="Deadlines, milestones, invoice due dates and meetings in one place." />

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        <a href="{{ $nav($prev) }}" class="btn-secondary btn-sm" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>
        <a href="{{ $nav(now()) }}" class="btn-secondary btn-sm">Today</a>
        <a href="{{ $nav($next) }}" class="btn-secondary btn-sm" aria-label="Next"><i class="bi bi-chevron-right"></i></a>
        <h2 class="ms-2 text-lg font-bold text-slate-900">{{ $label }}</h2>
    </div>
    <div class="flex gap-1" role="tablist">@foreach ($views as $k => $l)<a href="{{ $nav($date, $k) }}" role="tab" aria-selected="{{ $view === $k ? 'true' : 'false' }}" class="chip {{ $view === $k ? 'chip-active' : '' }}">{{ $l }}</a>@endforeach</div>
</div>

<div class="grid gap-6 {{ $portal ? '' : 'lg:grid-cols-[1fr_20rem]' }}">
<div>
@if ($view === 'month')
    <div class="card overflow-hidden">
        <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">@foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)<div class="py-2">{{ $d }}</div>@endforeach</div>
        <div class="grid grid-cols-7">
            @for ($d = $from->copy(); $d->lte($to); $d->addDay())
                @php $list = $byDay[$d->toDateString()] ?? collect(); $inMonth = $d->month === $date->month; @endphp
                <div class="min-h-24 border-b border-r border-slate-100 p-1.5 {{ $inMonth ? '' : 'bg-slate-50/60' }}">
                    <a href="{{ $nav($d, 'day') }}" class="inline-grid size-6 place-items-center rounded-full text-xs {{ $d->isToday() ? 'bg-brand-500 font-bold text-slate-900' : ($inMonth ? 'text-slate-700' : 'text-slate-400') }}" aria-label="{{ $d->format('d F') }}">{{ $d->day }}</a>
                    @foreach ($list->take(3) as $e)
                        @php [$ic, $cls] = $chip($e); @endphp
                        @if ($e['url'])<a href="{{ $e['url'] }}" class="mt-0.5 block truncate rounded px-1 py-0.5 text-[11px] {{ $cls }}" title="{{ $e['title'] }}"><i class="bi {{ $ic }}"></i> {{ $e['title'] }}</a>
                        @else<span class="mt-0.5 block truncate rounded px-1 py-0.5 text-[11px] {{ $cls }}" title="{{ $e['title'] }}"><i class="bi {{ $ic }}"></i> {{ $e['time'] }} {{ $e['title'] }}</span>@endif
                    @endforeach
                    @if ($list->count() > 3)<a href="{{ $nav($d, 'day') }}" class="mt-0.5 block text-[11px] text-slate-500 hover:underline">+{{ $list->count() - 3 }} more</a>@endif
                </div>
            @endfor
        </div>
    </div>
@else
    @php $days = $view === 'day' ? [$from->copy()] : ($view === 'week' ? collect(range(0, 6))->map(fn ($i) => $from->copy()->addDays($i))->all() : collect(range(0, 30))->map(fn ($i) => $from->copy()->addDays($i))->all()); @endphp
    <div class="card divide-y divide-slate-100">
        @php $shown = 0; @endphp
        @foreach ($days as $d)
            @php $list = $byDay[$d->toDateString()] ?? collect(); @endphp
            @continue($view === 'agenda' && $list->isEmpty())
            @php $shown++; @endphp
            <div class="px-5 py-3"><div class="mb-1 text-sm font-semibold {{ $d->isToday() ? 'text-brand-700' : 'text-slate-900' }}">{{ $d->format('l, d M') }}@if ($d->isToday()) <span class="text-xs font-normal">(today)</span>@endif</div>
                @forelse ($list as $e)
                    @php [$ic, $cls] = $chip($e); @endphp
                    <div class="mb-1 flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm {{ $cls }}"><i class="bi {{ $ic }}"></i><span class="min-w-0 flex-1 truncate">{{ $e['time'] }} {{ $e['title'] }}</span>
                        @if ($e['url'])<a href="{{ $e['url'] }}" class="text-xs underline">Open</a>@elseif (! $portal && isset($e['id']))<form method="POST" action="{{ route('calendar.events.destroy', $e['id']) }}">@csrf @method('DELETE')<button class="text-xs underline" aria-label="Remove {{ $e['title'] }}">Remove</button></form>@endif</div>
                @empty<p class="text-sm text-slate-400">Nothing scheduled.</p>@endforelse</div>
        @endforeach
        @if ($shown === 0)<p class="px-5 py-12 text-center text-sm text-slate-500">Nothing coming up. Deadlines, milestones and meetings will show here.</p>@endif
    </div>
@endif
<p class="mt-3 flex flex-wrap gap-3 text-xs text-slate-500">@foreach ($kinds as $k => [$ic, $cls])<span class="inline-flex items-center gap-1"><i class="bi {{ $ic }}"></i>{{ ucfirst($k) }}</span>@endforeach</p>
</div>

@unless ($portal)
<form method="POST" action="{{ route('calendar.events.store') }}" class="card h-fit space-y-3 p-5">@csrf
    <h2 class="font-semibold text-slate-900">Add a meeting or reminder</h2>
    <div><label class="label" for="ev-title">Title</label><input id="ev-title" name="title" required class="input"></div>
    <div class="grid grid-cols-2 gap-3"><div><label class="label" for="ev-date">Date</label><input id="ev-date" type="date" name="date" value="{{ $date->toDateString() }}" required class="input"></div><div><label class="label" for="ev-time">Time</label><input id="ev-time" type="time" name="time" class="input"></div></div>
    <div><label class="label" for="ev-kind">Type</label><select id="ev-kind" name="kind" class="input"><option value="meeting">Meeting</option><option value="reminder">Reminder</option></select></div>
    <div><label class="label" for="ev-client">Client <span class="font-normal text-slate-400">(optional)</span></label><select id="ev-client" name="client_id" class="input"><option value="">No client</option>@foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
    <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="visible_to_client" value="1" class="mt-1 rounded border-slate-300"> <span>Show in the client's calendar</span></label>
    <button class="btn-primary w-full">Add</button>
</form>
@endunless
</div>
@endsection
