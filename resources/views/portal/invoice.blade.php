@extends('layouts.app')
@section('title', $invoice->number)
@section('content')
@php $ds = $invoice->displayStatus(); $tone = ['sent' => 'amber', 'paid' => 'green', 'overdue' => 'red']; @endphp
<div class="mb-2 text-sm"><a href="{{ route('portal.invoices') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Invoices</a></div>
<x-page-title :title="'Invoice '.$invoice->number" :sub="'Due '.$invoice->due_date->format('d M Y')">
    <x-pill :tone="$tone[$ds] ?? 'slate'">{{ $ds === 'sent' ? 'Due' : ucfirst($ds) }}</x-pill>
    <a href="{{ route('invoices.pdf', $invoice) }}" class="btn-secondary"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
    <a href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener" class="btn-secondary"><i class="bi bi-printer"></i> Print</a>
</x-page-title>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card overflow-hidden lg:col-span-2"><iframe title="Invoice" src="{{ route('invoices.preview', $invoice) }}" class="h-[1100px] w-full bg-white"></iframe></div>
    <div class="space-y-6">
        <div class="card divide-y divide-slate-100 text-sm">
            <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Total</span><span class="font-semibold text-slate-900">{{ money($invoice->total_minor, $invoice->currency) }} {{ $invoice->currency }}</span></div>
            <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Paid</span><span class="text-slate-900">{{ money($invoice->amount_paid_minor, $invoice->currency) }}</span></div>
            <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Balance due</span><span class="font-semibold text-slate-900">{{ money($invoice->outstandingMinor(), $invoice->currency) }}</span></div>
        </div>
        @if ($payout && $invoice->outstandingMinor() > 0)
            <div class="card p-5 text-sm"><h2 class="mb-2 font-semibold text-slate-900">How to pay</h2>
                @foreach ($payout['lines'] as $row)<div class="flex justify-between gap-3 py-0.5"><span class="text-slate-500">{{ $row[0] }}</span><span class="text-right font-medium text-slate-900">{{ $row[1] }}</span></div>@endforeach
                <p class="mt-3 text-xs text-slate-500">Pay by bank transfer using the details above. Online payment is coming soon. Quote the invoice number as the reference.</p>
            </div>
        @endif
        @if ($invoice->payments->isNotEmpty())
            <div class="card"><div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Payments</h2></div>
                @foreach ($invoice->payments as $p)<div class="flex justify-between gap-2 border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="text-slate-700">{{ $p->paid_at?->format('d M Y') }} · {{ ucfirst(str_replace('_', ' ', $p->method)) }}</span><span class="font-semibold text-emerald-700">{{ money($p->amount_minor, $p->currency) }}</span></div>@endforeach</div>
        @endif
    </div>
</div>
@endsection
