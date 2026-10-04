@extends('layouts.app')
@section('title', $invoice->number)
@section('content')
@php
    $ds = $invoice->displayStatus();
    $tone = ['sent' => 'amber', 'paid' => 'green', 'overdue' => 'red', 'partial' => 'blue', 'void' => 'slate', 'refunded' => 'slate'];
    $label = ['sent' => 'Due', 'paid' => 'Paid', 'overdue' => 'Overdue', 'partial' => 'Partially paid', 'void' => 'Cancelled', 'refunded' => 'Refunded'];
    $owing = $invoice->outstandingMinor() > 0 && ! in_array($invoice->status, ['void', 'refunded'], true);
    $pending = $reports->where('status', 'pending');
@endphp
<div class="mb-2 text-sm"><a href="{{ route('portal.invoices') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Invoices</a></div>
<x-page-title :title="'Invoice '.$invoice->number" :sub="'Due '.$invoice->due_date->format('d M Y')">
    <x-pill :tone="$tone[$ds] ?? 'slate'">{{ $label[$ds] ?? ucfirst($ds) }}</x-pill>
    <a href="{{ route('invoices.pdf', $invoice) }}" class="btn-secondary"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
    <a href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener" class="btn-secondary"><i class="bi bi-printer"></i> Print</a>
</x-page-title>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card overflow-hidden lg:col-span-2"><iframe title="Invoice" src="{{ route('invoices.preview', $invoice) }}" class="h-[1100px] w-full bg-white"></iframe></div>
    <div class="space-y-6">
        <div class="card divide-y divide-slate-100 text-sm">
            <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Total</span><span class="font-semibold text-slate-900">{{ money($invoice->total_minor, $invoice->currency) }} {{ $invoice->currency }}</span></div>
            <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Paid</span><span class="text-slate-900">{{ money($invoice->amount_paid_minor, $invoice->currency) }}</span></div>
            <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Balance due</span><span class="font-semibold text-slate-900">{{ $owing ? money($invoice->outstandingMinor(), $invoice->currency) : money(0, $invoice->currency) }}</span></div>
        </div>

        @if ($owing && $payout)
            <div class="card p-5 text-sm"><h2 class="mb-2 font-semibold text-slate-900">How to pay</h2>
                @foreach ($payout['lines'] as $row)<div class="flex justify-between gap-3 py-0.5"><span class="text-slate-500">{{ $row[0] }}</span><span class="text-end font-medium text-slate-900">{{ $row[1] }}</span></div>@endforeach
                @if (! empty($payout['link']))<a href="{{ $payout['link'] }}" target="_blank" rel="noopener noreferrer" class="btn-primary mt-3 w-full"><i class="bi bi-credit-card"></i> Pay online</a>@endif
                <p class="mt-3 text-xs text-slate-500">Pay using the details above and quote <strong>{{ $invoice->number }}</strong> as the reference, then tell your freelancer below.</p>
            </div>
        @endif

        @if ($owing)
            <form method="POST" action="{{ route('portal.invoices.paid', $invoice) }}" class="card space-y-3 p-5" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">@csrf
                <h2 class="font-semibold text-slate-900">Already paid?</h2>
                @if ($pending->isNotEmpty())<p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">You told us about {{ $pending->count() }} payment{{ $pending->count() > 1 ? 's' : '' }}. Waiting for your freelancer to confirm.</p>@endif
                <button type="button" class="btn-secondary w-full" x-show="!open" @click="open = true">I have paid this invoice</button>
                <div x-show="open" x-cloak class="space-y-3">
                    <div><label class="label" for="pe-amount">Amount ({{ $invoice->currency }})</label><input id="pe-amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', \App\Support\Money::toInput($invoice->outstandingMinor())) }}" required class="input">@error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="grid grid-cols-2 gap-3"><div><label class="label" for="pe-date">Paid on</label><input id="pe-date" type="date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input"></div>
                        <div><label class="label" for="pe-method">Method</label><select id="pe-method" name="method" class="input">@foreach (['bank_transfer' => 'Bank transfer', 'upi' => 'UPI', 'paypal' => 'PayPal', 'wise' => 'Wise', 'cash' => 'Cash', 'other' => 'Other'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div></div>
                    <div><label class="label" for="pe-ref">Transaction reference</label><input id="pe-ref" name="reference" value="{{ old('reference') }}" required class="input">@error('reference')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <button class="btn-primary w-full">Send to my freelancer</button>
                </div>
            </form>
        @endif

        @if ($invoice->payments->isNotEmpty())
            <div class="card"><div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Payments</h2></div>
                @foreach ($invoice->payments as $p)<div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="text-slate-700">{{ $p->paid_at?->format('d M Y') }} · {{ ucfirst(str_replace('_', ' ', $p->method)) }}</span><span class="flex items-center gap-3"><a href="{{ route('invoices.receipt', [$invoice, $p]) }}" class="text-xs text-brand-600 hover:underline">Receipt</a><span class="font-semibold text-emerald-700">{{ money($p->amount_minor, $p->currency) }}</span></span></div>@endforeach</div>
        @endif

        <form method="POST" action="{{ route('portal.messages.store') }}" class="card space-y-3 p-5">@csrf
            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
            <h2 class="font-semibold text-slate-900">Ask a question</h2>
            <label class="sr-only" for="q-body">Your question</label><textarea id="q-body" name="body" rows="3" required class="input" placeholder="Ask about this invoice. It goes to your freelancer in Messages."></textarea>
            <button class="btn-secondary w-full"><i class="bi bi-send"></i> Send question</button>
        </form>
    </div>
</div>
@endsection
