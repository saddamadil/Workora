@extends('layouts.app')
@section('title', $invoice->number)
@section('content')
@php
    $ds = $invoice->displayStatus();
    $tone = ['draft' => 'slate', 'sent' => 'amber', 'paid' => 'green', 'overdue' => 'red', 'rejected' => 'orange', 'void' => 'slate'];
    $label = ['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue', 'rejected' => 'Sent back', 'void' => 'Void'];
    $cur = $invoice->currency;
@endphp
<div class="mb-2 text-sm"><a href="{{ route('invoices.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Invoices</a></div>
<x-page-title :title="$invoice->number" :sub="($invoice->client?->name ?? $invoice->organization->name).' · from '.$invoice->freelancer->name.' · issued '.$invoice->issue_date->format('d M Y')">
    <x-pill :tone="$tone[$ds] ?? 'slate'">{{ $label[$ds] ?? ucfirst($ds) }}</x-pill>
    <a href="{{ route('invoices.pdf', $invoice) }}" class="btn-secondary btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <a href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener" class="btn-secondary btn-sm"><i class="bi bi-printer"></i> Print</a>
    @if ($canDuplicate)<form method="POST" action="{{ route('invoices.duplicate', $invoice) }}">@csrf<button class="btn-secondary btn-sm"><i class="bi bi-files"></i> Duplicate</button></form>@endif
</x-page-title>

@if ($invoice->status === 'rejected' && $invoice->rejection_reason)
    <div class="mb-5 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900"><strong>Sent back:</strong> {{ $invoice->rejection_reason }}</div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
<div class="space-y-6 lg:col-span-2">
    <div class="card overflow-hidden"><iframe title="Invoice" src="{{ route('invoices.preview', $invoice) }}" class="h-[1100px] w-full bg-white"></iframe></div>

    @if ($invoice->payments->isNotEmpty())
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Payments</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($invoice->payments as $p)
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3"><span class="text-slate-700">{{ $p->paid_at?->format('d M Y') }} · {{ ucfirst(str_replace('_', ' ', $p->method)) }}@if ($p->reference) · {{ $p->reference }}@endif</span><span class="font-semibold text-emerald-700">{{ money($p->amount_minor, $p->currency) }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

<div class="space-y-6">
    @can('approve', $invoice)
        <div class="card space-y-3 border-amber-200 p-5"><h2 class="font-semibold text-slate-900">Review</h2>
            <form method="POST" action="{{ route('invoices.approve', $invoice) }}">@csrf<button class="btn-primary w-full"><i class="bi bi-check2-circle"></i> Approve invoice</button></form>
            <form method="POST" action="{{ route('invoices.reject', $invoice) }}" class="space-y-2 border-t border-slate-100 pt-3">@csrf
                <input name="reason" required class="input" placeholder="Why is it being sent back?" aria-label="Reason"><button class="btn-secondary w-full">Send back</button></form></div>
    @endcan

    @can('recordPayment', $invoice)
        <div class="card space-y-3 p-5">
            <h2 class="font-semibold text-slate-900">Payment</h2>
            <p class="text-xs text-slate-500">Workora records payments you have made. It does not move money. Still owed: <strong>{{ money($invoice->outstandingMinor(), $cur) }}</strong></p>
            <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="space-y-3">@csrf
                <div><label class="label" for="amount">Amount ({{ $cur }})</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', \App\Support\Money::toInput($invoice->outstandingMinor())) }}" required class="input"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label" for="paid_on">Paid on</label><input id="paid_on" type="date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input"></div>
                    <div><label class="label" for="method">Method</label><select id="method" name="method" class="input">@foreach (['bank_transfer' => 'Bank transfer', 'upi' => 'UPI', 'paypal' => 'PayPal', 'wise' => 'Wise', 'cash' => 'Cash', 'other' => 'Other'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                </div>
                <div><label class="label" for="reference">Reference</label><input id="reference" name="reference" class="input"></div>
                <button class="btn-primary w-full">Record payment</button>
            </form>
            <form method="POST" action="{{ route('invoices.mark-paid', $invoice) }}" class="border-t border-slate-100 pt-3">@csrf
                <input type="hidden" name="method" value="bank_transfer"><input type="hidden" name="paid_on" value="{{ now()->toDateString() }}">
                <button class="btn-secondary w-full"><i class="bi bi-check-all"></i> Mark as paid in full</button></form>
        </div>
    @endcan

    @if ($payout)
        <div class="card p-5 text-sm"><h2 class="mb-2 font-semibold text-slate-900">Pay to</h2>
            @foreach ($payout['lines'] as $row)<div class="flex justify-between gap-3 py-0.5"><span class="text-slate-500">{{ $row[0] }}</span><span class="font-medium text-slate-900">{{ $row[1] }}</span></div>@endforeach
        </div>
    @endif

    <div class="card divide-y divide-slate-100 text-sm">
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Total</span><span class="font-semibold text-slate-900">{{ money($invoice->total_minor, $cur) }}</span></div>
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Due</span><span class="font-medium text-slate-900">{{ $invoice->due_date->format('d M Y') }}</span></div>
        @if ($invoice->approvedBy)<div class="flex justify-between px-5 py-3"><span class="text-slate-500">Approved by</span><span class="font-medium text-slate-900">{{ $invoice->approvedBy->name }}</span></div>@endif
    </div>
</div>
</div>
@endsection
