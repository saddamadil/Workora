@extends('layouts.app')
@section('title', 'Exchange rates')
@section('content')
<x-page-title title="Exchange rates" sub="Rates you enter yourself. They are suggested on international invoices for the INR equivalent. Nothing is converted silently." />
<div class="grid gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-2" aria-labelledby="er"><div class="border-b border-slate-100 px-5 py-3"><h2 id="er" class="font-semibold text-slate-900">Your rates</h2></div>
        @forelse ($rates as $r)<div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span><strong class="text-slate-900">1 {{ $r->from_currency }} = {{ rtrim(rtrim(number_format($r->rate, 6), '0'), '.') }} {{ $r->to_currency }}</strong> <span class="text-slate-500">· from {{ $r->effective_on->format('d M Y') }}@if ($r->note) · {{ $r->note }}@endif</span></span>
            <form method="POST" action="{{ route('settings.exchange-rates.destroy', $r) }}">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Remove</button></form></div>
        @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No rates yet. Add one, for example USD to INR.</p>@endforelse
    </section>
    <form method="POST" action="{{ route('settings.exchange-rates.store') }}" class="card h-fit space-y-3 p-5">@csrf
        <h2 class="font-semibold text-slate-900">Add a rate</h2>
        <div class="grid grid-cols-2 gap-3"><div><label class="label" for="fr">From</label><select id="fr" name="from_currency" class="input">@foreach ($currencies as $c)<option @selected($c === 'USD')>{{ $c }}</option>@endforeach</select></div>
            <div><label class="label" for="to">To</label><select id="to" name="to_currency" class="input">@foreach ($currencies as $c)<option @selected($c === 'INR')>{{ $c }}</option>@endforeach</select></div></div>
        <div><label class="label" for="rt">Rate (1 unit equals)</label><input id="rt" name="rate" type="number" step="0.000001" min="0" required class="input">@error('rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @error('to_currency')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="ef">Effective from</label><input id="ef" type="date" name="effective_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required class="input"></div>
        <div><label class="label" for="nt">Note</label><input id="nt" name="note" class="input" placeholder="e.g. RBI reference rate"></div>
        <button class="btn-primary w-full">Save rate</button>
    </form>
</div>
@endsection
