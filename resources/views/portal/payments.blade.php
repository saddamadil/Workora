@extends('layouts.app')
@section('title', 'Payments')
@section('content')
@php $fmt = fn (array $by) => $by ? collect($by)->map(fn ($m, $c) => money($m, $c))->implode(' + ') : '—'; @endphp
<x-page-title title="Payments" sub="What is due, and everything you have paid." />
<div class="mb-6 grid gap-3 sm:grid-cols-2">
    <x-stat label="Amount due" :value="$fmt($due)" icon="bi-hourglass-split" :tone="$due ? 'amber' : 'slate'" />
    <x-stat label="Next due date" :value="$nextDue ? $nextDue->due_date->format('d M Y') : '—'" icon="bi-calendar-event" tone="slate" :hint="$nextDue?->number" :href="$nextDue ? route('portal.invoice', $nextDue) : null" />
</div>
@if ($open->isNotEmpty())
<section class="card mb-6" aria-labelledby="du"><div class="border-b border-slate-100 px-5 py-3"><h2 id="du" class="font-semibold text-slate-900">To pay</h2></div>
    @foreach ($open as $i)<a href="{{ route('portal.invoice', $i) }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50"><span class="font-medium text-slate-900">{{ $i->number }} <span class="font-normal text-slate-500">· due {{ $i->due_date->format('d M Y') }}</span></span><span class="font-semibold text-slate-900">{{ money($i->outstandingMinor(), $i->currency) }}</span></a>@endforeach</section>
@endif
<section class="card" aria-labelledby="ph"><div class="border-b border-slate-100 px-5 py-3"><h2 id="ph" class="font-semibold text-slate-900">Payment history</h2></div>
    @forelse ($history as $p)<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="text-slate-700">{{ $p->paid_at?->format('d M Y') }} · {{ $p->invoice->number }} · {{ ucfirst(str_replace('_', ' ', $p->method)) }}</span><span class="flex items-center gap-3"><a class="text-xs text-brand-600 hover:underline" href="{{ route('invoices.receipt', [$p->invoice_id, $p]) }}">Receipt</a><span class="font-semibold text-emerald-700">{{ money($p->amount_minor, $p->currency) }}</span></span></div>
    @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No payments yet.</p>@endforelse</section>
@endsection
