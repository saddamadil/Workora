@extends('layouts.app')
@section('title', $mine ? 'Earnings' : 'Payments')
@section('content')
<x-page-title :title="$mine ? 'Earnings' : 'Payments'" :sub="$mine ? 'What you are owed and what you have been paid.' : 'What you owe your freelancers and what you have paid.'" />
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat :label="$mine ? 'Owed to you' : 'Still to pay'" :value="money($owedMinor, $org->base_currency)" icon="bi-wallet2" tone="amber" />
    <x-stat label="Overdue" :value="money($overdueMinor, $org->base_currency)" icon="bi-exclamation-triangle" :tone="$overdueMinor ? 'red' : 'slate'" />
    <x-stat label="Paid this month" :value="money($paidMonthMinor, $org->base_currency)" icon="bi-cash-coin" tone="green" />
    <x-stat label="Paid all time" :value="money($paidTotalMinor, $org->base_currency)" icon="bi-bank" tone="slate" />
</div>

<div class="card mb-6">
    <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">{{ $mine ? 'Waiting to be paid' : 'Approved invoices to pay' }}</h2></div>
    @if ($owed->isEmpty())<p class="px-5 py-8 text-center text-sm text-slate-500">Nothing outstanding.</p>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach ($owed as $i)
                <li><a href="{{ route('invoices.show', $i) }}" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-slate-50">
                    <div class="min-w-0 flex-1"><div class="font-medium text-slate-900">{{ $i->number }} @unless ($mine)<span class="font-normal text-slate-500">· {{ $i->freelancer->name }}</span>@endunless</div>
                        <div class="text-xs {{ $i->isOverdue() ? 'font-semibold text-red-600' : 'text-slate-500' }}">Due {{ $i->due_date->format('d M Y') }}{{ $i->isOverdue() ? ' · overdue' : '' }}</div></div>
                    <span class="font-semibold text-slate-900">{{ money($i->outstandingMinor(), $i->currency) }}</span></a></li>
            @endforeach
        </ul>
    @endif
</div>

<div class="card">
    <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Payment history</h2></div>
    <ul class="divide-y divide-slate-100">
        @forelse ($payments as $p)
            <li class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                <div class="min-w-0 flex-1"><div class="font-medium text-slate-900">{{ $p->paid_at?->format('d M Y') }} · {{ $p->invoice?->number }} @unless ($mine)<span class="font-normal text-slate-500">· {{ $p->payee->name }}</span>@endunless</div>
                    <div class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $p->method)) }}@if ($p->reference) · {{ $p->reference }}@endif</div></div>
                <span class="font-semibold text-emerald-700">{{ money($p->amount_minor, $p->currency) }}</span></li>
        @empty<li class="px-5 py-8 text-center text-sm text-slate-500">No payments yet.</li>@endforelse
    </ul>
</div>
<div class="mt-6">{{ $payments->links() }}</div>
@endsection
