@extends('layouts.app')
@section('title', $invoice->exists ? 'Invoice '.$invoice->number : 'New invoice')
@section('content')
@php
    $steps = [1 => 'Invoice details', 2 => 'Services', 3 => 'Preview & send'];
@endphp
<div class="mb-2 text-sm"><a href="{{ route('invoices.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Invoices</a></div>
<x-page-title :title="$invoice->exists ? $invoice->number : 'New invoice'" :sub="$invoice->exists ? 'Draft · '.($issuer?->name) : 'Three quick steps: details, services, preview.'">
    @if ($invoice->exists)
        @can('delete', $invoice)
            <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this draft? Time and milestones go back to the pool.')">@csrf @method('DELETE')
                <button class="btn-secondary text-red-600"><i class="bi bi-trash"></i> Delete draft</button></form>
        @endcan
    @endif
</x-page-title>

@if ($invoice->status === 'rejected' && $invoice->rejection_reason)
    <div class="mb-5 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900"><strong>Sent back:</strong> {{ $invoice->rejection_reason }}</div>
@endif

<ol class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Steps">
    @foreach ($steps as $n => $name)
        @php $reachable = $invoice->exists || $n === 1; @endphp
        <li>
            <{{ $reachable && $n !== $step ? 'a href='.($invoice->exists ? route('invoices.edit', [$invoice, 'step' => $n]) : '#') : 'span' }}
               @if ($n === $step) aria-current="step" @endif
               class="flex items-center gap-2 rounded-full border px-3 py-1.5 {{ $n === $step ? 'border-brand-600 bg-brand-50 font-semibold text-brand-700' : ($n < $step ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-500') }}">
                <span class="grid size-5 place-items-center rounded-full bg-white text-xs font-bold">{{ $n < $step ? '✓' : $n }}</span> {{ $name }}
            </{{ $reachable && $n !== $step ? 'a' : 'span' }}>
        </li>
    @endforeach
</ol>

@include('invoices.wizard._step'.$step)
@endsection
