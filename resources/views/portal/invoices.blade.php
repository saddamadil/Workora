@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
@php
    $fmt = fn (array $by) => $by ? collect($by)->map(fn ($m, $c) => money($m, $c))->implode(' + ') : '—';
    $tone = ['sent' => 'amber', 'paid' => 'green', 'overdue' => 'red', 'partial' => 'blue', 'void' => 'slate', 'refunded' => 'slate'];
    $label = ['sent' => 'Due', 'paid' => 'Paid', 'overdue' => 'Overdue', 'partial' => 'Partially paid', 'void' => 'Cancelled', 'refunded' => 'Refunded'];
@endphp
<x-page-title title="Invoices" sub="Everything you have been billed, and what is still due." />
<div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Next due date" :value="$nextDue ? $nextDue->due_date->format('d M Y') : '—'" icon="bi-calendar-event" tone="slate" :hint="$nextDue?->number" />
    <x-stat label="Amount due" :value="$fmt($due)" icon="bi-hourglass-split" :tone="$due ? 'amber' : 'slate'" />
    <x-stat label="Overdue" :value="$overdue" icon="bi-exclamation-circle" :tone="$overdue ? 'red' : 'slate'" hint="invoices" />
    <x-stat label="Paid so far" :value="$fmt($paid)" icon="bi-check-circle" tone="green" />
</div>
<div class="card divide-y divide-slate-100">
    @forelse ($invoices as $i)
        @php $ds = $i->displayStatus(); @endphp
        <a href="{{ route('portal.invoice', $i) }}" class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-slate-50">
            <div class="min-w-0 flex-1"><div class="font-semibold text-slate-900">{{ $i->number }}</div><div class="text-sm text-slate-500">Issued {{ $i->issue_date->format('d M Y') }} · due {{ $i->due_date->format('d M Y') }}</div></div>
            <span class="font-semibold text-slate-900">{{ money($i->total_minor, $i->currency) }}</span>
            <x-pill :tone="$tone[$ds] ?? 'slate'">{{ $label[$ds] ?? ucfirst($ds) }}</x-pill>
        </a>
    @empty
        <p class="px-5 py-12 text-center text-sm text-slate-500">No invoices yet. They appear here when your freelancer sends one.</p>
    @endforelse
</div>
@if ($history->isNotEmpty())
<section class="card mt-6" aria-labelledby="ph"><div class="border-b border-slate-100 px-5 py-3"><h2 id="ph" class="font-semibold text-slate-900">Payment history</h2></div>
    @foreach ($history as $p)<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="text-slate-700">{{ $p->paid_at?->format('d M Y') }} · {{ $p->invoice->number }} · {{ ucfirst(str_replace('_', ' ', $p->method)) }}</span><span class="flex items-center gap-3"><a class="text-xs text-brand-600 hover:underline" href="{{ route('invoices.receipt', [$p->invoice_id, $p]) }}">Receipt</a><span class="font-semibold text-emerald-700">{{ money($p->amount_minor, $p->currency) }}</span></span></div>@endforeach
</section>
@endif
@endsection
