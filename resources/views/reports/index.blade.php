@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@php
    $fmt = fn (array $by) => $by ? collect($by)->map(fn ($m, $c) => money($m, $c))->implode(' + ') : '—';
    $max = max(1, $monthly->max(fn ($m) => $m['by'][$monthlyCurrency] ?? 0));
@endphp
<x-page-title title="Reports" sub="Simple numbers about your business. Amounts in different currencies are never added together.">
    <div class="flex gap-1">@foreach ($ranges as $k => $l)<a href="{{ route('reports.index', ['range' => $k]) }}" class="chip {{ $range === $k ? 'chip-active' : '' }}">{{ $l }}</a>@endforeach</div>
</x-page-title>

<div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Revenue received" :value="$fmt($receivedTotals)" icon="bi-cash-coin" tone="green" />
    <x-stat label="Invoiced" :value="$fmt($invoiced)" icon="bi-receipt" tone="slate" :hint="($paidCount + $openCount).' invoices'" />
    <x-stat label="Outstanding" :value="$fmt($outstanding)" icon="bi-hourglass-split" :tone="$outstanding ? 'amber' : 'slate'" :hint="$openCount.' unpaid'" />
    <x-stat label="Overdue" :value="$fmt($overdue)" icon="bi-exclamation-circle" :tone="$overdue ? 'red' : 'slate'" :hint="$overdueCount.' overdue'" />
</div>

<section class="card mb-6" aria-labelledby="mr">
    <div class="border-b border-slate-100 px-5 py-3"><h2 id="mr" class="font-semibold text-slate-900">Monthly revenue <span class="font-normal text-slate-500">({{ $monthlyCurrency }}, last 12 months)</span></h2></div>
    <div class="flex h-48 items-end gap-2 px-5 pb-3 pt-6" role="img" aria-label="Bar chart of money received per month">
        @foreach ($monthly as $m)@php $v = $m['by'][$monthlyCurrency] ?? 0; @endphp
            <div class="flex flex-1 flex-col items-center justify-end gap-1"><span class="text-[10px] text-slate-500">{{ $v ? money($v, $monthlyCurrency) : '' }}</span><div class="w-full rounded-t bg-brand-500" style="height: {{ max(2, $v / $max * 100) }}%"></div><span class="text-[11px] text-slate-500">{{ $m['label'] }}</span></div>
        @endforeach
    </div>
    <table class="sr-only"><caption>Money received per month</caption><tbody>@foreach ($monthly as $m)<tr><th>{{ $m['label'] }}</th><td>{{ $fmt($m['by']) }}</td></tr>@endforeach</tbody></table>
</section>

<div class="mb-6 grid gap-6 lg:grid-cols-2">
    <section class="card" aria-labelledby="bc"><div class="border-b border-slate-100 px-5 py-3"><h2 id="bc" class="font-semibold text-slate-900">Revenue by client</h2></div>
        @forelse ($clients as $c)<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="font-medium text-slate-900">{{ $c['name'] }}</span><span class="text-slate-600">{{ money($c['received'], $c['currency']) }} received · {{ money($c['outstanding'], $c['currency']) }} due</span></div>
        @empty<p class="px-5 py-8 text-center text-sm text-slate-500">No invoices in this period.</p>@endforelse</section>
    <section class="card" aria-labelledby="tc"><div class="border-b border-slate-100 px-5 py-3"><h2 id="tc" class="font-semibold text-slate-900">Top clients</h2></div>
        @forelse ($topClients as $i => $c)<div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="grid size-6 place-items-center rounded-full bg-slate-100 text-xs font-bold">{{ $i + 1 }}</span><span class="flex-1 font-medium text-slate-900">{{ $c['name'] }}</span><span class="text-slate-600">{{ money($c['received'], $c['currency']) }}</span></div>
        @empty<p class="px-5 py-8 text-center text-sm text-slate-500">Nothing received yet.</p>@endforelse</section>
</div>

<div class="mb-6 grid gap-6 lg:grid-cols-2">
    <section class="card" aria-labelledby="cb"><div class="border-b border-slate-100 px-5 py-3"><h2 id="cb" class="font-semibold text-slate-900">Currency breakdown</h2></div>
        @forelse ($currencies as $c)<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="font-semibold text-slate-900">{{ $c['currency'] }}</span><span class="text-slate-600">Invoiced {{ money($c['invoiced'], $c['currency']) }} · received {{ money($c['received'], $c['currency']) }} · due {{ money($c['outstanding'], $c['currency']) }}</span></div>
        @empty<p class="px-5 py-8 text-center text-sm text-slate-500">No invoices yet.</p>@endforelse</section>
    <section class="card" aria-labelledby="hw"><div class="border-b border-slate-100 px-5 py-3"><h2 id="hw" class="font-semibold text-slate-900">Hours worked</h2></div>
        <dl class="grid grid-cols-3 gap-3 px-5 py-4 text-sm"><div><dt class="text-xs text-slate-500">Total</dt><dd class="font-semibold text-slate-900">{{ hours($hours['total']) }}</dd></div><div><dt class="text-xs text-slate-500">Billable</dt><dd class="font-semibold text-slate-900">{{ hours($hours['billable']) }}</dd></div><div><dt class="text-xs text-slate-500">Non-billable</dt><dd class="font-semibold text-slate-900">{{ hours($hours['non']) }}</dd></div></dl>
        @foreach ($hoursByClient as $name => $min)<div class="flex justify-between border-t border-slate-100 px-5 py-2 text-sm"><span>{{ $name }}</span><span class="text-slate-600">{{ hours($min) }}</span></div>@endforeach</section>
</div>

<section class="card" aria-labelledby="pp"><div class="border-b border-slate-100 px-5 py-3"><h2 id="pp" class="font-semibold text-slate-900">Project profitability</h2><p class="text-xs text-slate-500">What you invoiced on a project divided by the hours logged on it.</p></div>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Project</th><th class="px-3 py-3">Client</th><th class="px-3 py-3 text-right">Hours</th><th class="px-3 py-3 text-right">Invoiced</th><th class="px-5 py-3 text-right">Per hour</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@forelse ($projects as $p)<tr><td class="px-5 py-2.5 font-medium text-slate-900">{{ $p['name'] }}</td><td class="px-3 py-2.5 text-slate-600">{{ $p['client'] ?: '—' }}</td><td class="px-3 py-2.5 text-right">{{ hours($p['minutes']) }}</td><td class="px-3 py-2.5 text-right">{{ money($p['invoiced'], $p['currency']) }}</td><td class="px-5 py-2.5 text-right">{{ $p['effective'] ? money($p['effective'], $p['currency']) : '—' }}</td></tr>
        @empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Log time against a project to see how it performs.</td></tr>@endforelse</tbody></table></div></section>
@endsection
