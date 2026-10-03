@extends('layouts.app')
@section('title', $invoice->number)
@section('content')
@php
    $tone = ['draft' => 'slate', 'submitted' => 'amber', 'under_review' => 'amber', 'approved' => 'blue', 'rejected' => 'orange', 'partially_paid' => 'blue', 'paid' => 'green', 'void' => 'slate'];
    $cur = $invoice->currency;
@endphp
<div class="mb-2 text-sm print:hidden"><a href="{{ route('invoices.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Invoices</a></div>
<x-page-title :title="$invoice->number" :sub="($role->isFreelancer() ? 'To '.$org->name : 'From '.$invoice->freelancer->name).' · issued '.$invoice->issue_date->format('d M Y')">
    <x-pill :tone="$tone[$invoice->status] ?? 'slate'">{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</x-pill>
    <button type="button" onclick="window.print()" class="btn-secondary btn-sm print:hidden"><i class="bi bi-printer"></i> Print</button>
</x-page-title>

@if ($invoice->status === 'rejected' && $invoice->rejection_reason)
    <div class="mb-5 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900"><strong>Sent back:</strong> {{ $invoice->rejection_reason }}</div>
@endif

<div class="grid gap-6 lg:grid-cols-3">
<div class="space-y-6 lg:col-span-2">
    <div class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Description</th><th class="px-3 py-3 text-right">Qty</th><th class="px-3 py-3 text-right">Rate</th><th class="px-5 py-3 text-right">Amount</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoice->items as $item)
                    <tr><td class="px-5 py-2.5 text-slate-800">{{ $item->description }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-slate-600">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit === 'hours' ? 'h' : '' }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-slate-600">{{ money($item->unit_rate_minor, $cur) }}</td>
                        <td class="whitespace-nowrap px-5 py-2.5 text-right font-medium text-slate-900">{{ money($item->amount_minor, $cur) }}</td>
                        <td class="pr-4 print:hidden">@if ($canEdit)<form method="POST" action="{{ route('invoices.items.remove', [$invoice, $item]) }}">@csrf @method('DELETE')<button class="text-slate-300 hover:text-red-600" aria-label="Remove line"><i class="bi bi-x-lg"></i></button></form>@endif</td></tr>
                @empty<tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No lines yet.</td></tr>@endforelse
            </tbody>
            <tfoot class="text-sm">
                <tr class="border-t border-slate-200"><td colspan="3" class="px-5 py-2 text-right text-slate-500">Subtotal</td><td class="px-5 py-2 text-right text-slate-800">{{ money($invoice->subtotal_minor, $cur) }}</td><td></td></tr>
                @if ($invoice->tax_minor)<tr><td colspan="3" class="px-5 py-2 text-right text-slate-500">Tax ({{ rtrim(rtrim($invoice->tax_rate, '0'), '.') }}%)</td><td class="px-5 py-2 text-right text-slate-800">{{ money($invoice->tax_minor, $cur) }}</td><td></td></tr>@endif
                <tr><td colspan="3" class="px-5 py-2 text-right font-semibold text-slate-900">Total</td><td class="px-5 py-2 text-right text-lg font-bold text-slate-900">{{ money($invoice->total_minor, $cur) }}</td><td></td></tr>
                @if ($invoice->amount_paid_minor)<tr><td colspan="3" class="px-5 py-2 text-right text-slate-500">Paid</td><td class="px-5 py-2 text-right text-emerald-700">{{ money($invoice->amount_paid_minor, $cur) }}</td><td></td></tr>
                    <tr><td colspan="3" class="px-5 pb-3 text-right font-semibold text-slate-900">Still owed</td><td class="px-5 pb-3 text-right font-bold text-slate-900">{{ money($invoice->outstandingMinor(), $cur) }}</td><td></td></tr>@endif
            </tfoot>
        </table>
    </div>

    @if ($invoice->notes)<p class="card whitespace-pre-line p-5 text-sm text-slate-700">{{ $invoice->notes }}</p>@endif

    @if ($canEdit)
        <div class="card space-y-4 p-5 print:hidden">
            <h2 class="font-semibold text-slate-900">Add to this invoice</h2>
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('invoices.import-time', $invoice) }}">@csrf
                    <button class="btn-secondary" @disabled(! $availableMinutes)><i class="bi bi-stopwatch"></i> Approved time <span class="text-slate-500">({{ hours($availableMinutes) }})</span></button></form>
                @if ($invoice->contract?->type === 'milestone')
                    <form method="POST" action="{{ route('invoices.import-milestones', $invoice) }}">@csrf
                        <button class="btn-secondary" @disabled($availableMilestones->isEmpty())><i class="bi bi-flag"></i> Approved milestones ({{ $availableMilestones->count() }})</button></form>
                @endif
            </div>
            <form method="POST" action="{{ route('invoices.items.add', $invoice) }}" class="grid gap-3 sm:grid-cols-12">@csrf
                <input name="description" required placeholder="Something else, e.g. fixed fee" class="input sm:col-span-5" aria-label="Description">
                <input name="quantity" type="number" step="0.01" min="0.01" value="1" required class="input sm:col-span-2" aria-label="Quantity">
                <select name="unit" class="input sm:col-span-2" aria-label="Unit"><option value="items">items</option><option value="hours">hours</option><option value="fixed">fixed</option></select>
                <input name="unit_rate" type="number" step="0.01" min="0" required placeholder="Rate" class="input sm:col-span-2" aria-label="Rate">
                <button class="btn-secondary sm:col-span-1" aria-label="Add line"><i class="bi bi-plus-lg"></i></button>
            </form>
            <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-4">@csrf @method('PUT')
                <div><label class="label" for="due_date">Due date</label><input id="due_date" type="date" name="due_date" value="{{ $invoice->due_date->format('Y-m-d') }}" class="input"></div>
                <div><label class="label" for="tax_rate">Tax %</label><input id="tax_rate" type="number" step="0.01" min="0" max="100" name="tax_rate" value="{{ $invoice->tax_rate }}" class="input"></div>
                <div class="sm:col-span-2"><label class="label" for="notes">Note to the company</label><input id="notes" name="notes" value="{{ $invoice->notes }}" class="input"></div>
                <div class="sm:col-span-4 flex justify-end"><button class="btn-secondary">Save details</button></div>
            </form>
        </div>
    @endif

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

<div class="space-y-6 print:hidden">
    @can('submit', $invoice)
        <div class="card p-5"><h2 class="font-semibold text-slate-900">Ready?</h2><p class="mt-1 text-sm text-slate-500">Once sent, you cannot change the invoice unless it is sent back.</p>
            <form method="POST" action="{{ route('invoices.submit', $invoice) }}" class="mt-3">@csrf<button class="btn-primary w-full"><i class="bi bi-send"></i> Send for approval</button></form></div>
    @endcan
    @can('delete', $invoice)
        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this draft? Time and milestones go back to your pool.')">@csrf @method('DELETE')<button class="btn-secondary w-full text-red-600"><i class="bi bi-trash"></i> Delete draft</button></form>
    @endcan

    @can('approve', $invoice)
        <div class="card space-y-3 border-amber-200 p-5"><h2 class="font-semibold text-slate-900"><i class="bi bi-eye text-amber-500"></i> Review</h2>
            <form method="POST" action="{{ route('invoices.approve', $invoice) }}">@csrf<button class="btn-primary w-full"><i class="bi bi-check2-circle"></i> Approve invoice</button></form>
            <form method="POST" action="{{ route('invoices.reject', $invoice) }}" class="space-y-2 border-t border-slate-100 pt-3">@csrf
                <input name="reason" required class="input" placeholder="Why is it being sent back?" aria-label="Reason"><button class="btn-secondary w-full">Send back</button></form></div>
    @endcan

    @can('recordPayment', $invoice)
        <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}" class="card space-y-3 p-5">@csrf
            <h2 class="font-semibold text-slate-900">Record a payment</h2>
            <p class="text-xs text-slate-500">Workora records payments you have made. It does not move money.</p>
            <div><label class="label" for="amount">Amount ({{ $cur }})</label><input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', \App\Support\Money::toInput($invoice->outstandingMinor())) }}" required class="input"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="paid_on">Paid on</label><input id="paid_on" type="date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input"></div>
                <div><label class="label" for="method">Method</label><select id="method" name="method" class="input">@foreach (['bank_transfer' => 'Bank transfer', 'upi' => 'UPI', 'paypal' => 'PayPal', 'wise' => 'Wise', 'cash' => 'Cash', 'other' => 'Other'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            </div>
            <div><label class="label" for="reference">Reference <span class="font-normal text-slate-400">(transaction ID)</span></label><input id="reference" name="reference" class="input"></div>
            <button class="btn-primary w-full">Record payment</button>
        </form>
    @endcan

    <div class="card divide-y divide-slate-100 text-sm">
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Freelancer</span><span class="font-medium text-slate-900">{{ $invoice->freelancer->name }}</span></div>
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Contract</span>@if ($invoice->contract && ($role->isFreelancer() || Gate::allows('see-money')))<a class="font-medium text-brand-600 hover:underline" href="{{ route('contracts.show', $invoice->contract) }}">{{ $invoice->contract->reference }}</a>@else<span>—</span>@endif</div>
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Due</span><span class="font-medium {{ $invoice->isOverdue() && in_array($invoice->status, ['approved', 'partially_paid']) ? 'text-red-600' : 'text-slate-900' }}">{{ $invoice->due_date->format('d M Y') }}</span></div>
        @if ($invoice->approvedBy)<div class="flex justify-between px-5 py-3"><span class="text-slate-500">Approved by</span><span class="font-medium text-slate-900">{{ $invoice->approvedBy->name }}</span></div>@endif
    </div>
</div>
</div>
@endsection
