@extends('layouts.app')
@section('title', $contract->title)
@section('content')
@php $tone = ['draft' => 'slate', 'sent' => 'amber', 'active' => 'green', 'declined' => 'red', 'terminated' => 'slate', 'completed' => 'blue']; $mTone = ['pending' => 'slate', 'submitted' => 'amber', 'approved' => 'green', 'invoiced' => 'blue', 'paid' => 'green']; @endphp
<div class="mb-2 text-sm"><a href="{{ route('contracts.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Contracts</a></div>
<x-page-title :title="$contract->title" :sub="$contract->reference.' · '.($isFreelancer ? $org->name : $contract->freelancer->name)">
    <x-pill :tone="$tone[$contract->status] ?? 'slate'">{{ ucfirst($contract->status) }}</x-pill>
    @if ($canManage && $contract->status === 'draft')<a href="{{ route('contracts.edit', $contract) }}" class="btn-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>@endif
</x-page-title>

@if ($isFreelancer && $contract->status === 'sent')
    <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center">
        <div class="flex-1 text-sm text-amber-900"><strong>{{ $org->name }} sent you this contract.</strong> Read the terms below. Accepting means you agree to them.</div>
        <form method="POST" action="{{ route('contracts.decline', $contract) }}" onsubmit="return confirm('Decline this contract?')">@csrf<button class="btn-secondary">Decline</button></form>
        <form method="POST" action="{{ route('contracts.accept', $contract) }}">@csrf<button class="btn-primary"><i class="bi bi-pen"></i> Accept contract</button></form>
    </div>
@endif

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Type" :value="ucfirst($contract->type)" icon="bi-diagram-3" tone="slate" />
    <x-stat label="Pay" :value="match($contract->type) { 'hourly' => money($contract->hourly_rate_minor, $contract->currency).'/hr', 'fixed' => money($contract->fixed_amount_minor, $contract->currency), 'retainer' => money($contract->retainer_amount_minor, $contract->currency).' / '.$contract->payment_cycle, default => money($contract->valueMinor(), $contract->currency) }" icon="bi-cash-coin" tone="green" />
    <x-stat label="Runs" :value="$contract->starts_on->format('d M Y').($contract->ends_on ? ' → '.$contract->ends_on->format('d M Y') : ' → open')" icon="bi-calendar-range" tone="slate" />
    <x-stat label="Paid within" :value="$contract->payment_terms_days.' days'" :hint="ucfirst(str_replace('_', ' ', $contract->payment_cycle)).' invoices'" icon="bi-hourglass-split" tone="amber" />
</div>

<div class="grid gap-6 lg:grid-cols-3">
<div class="space-y-6 lg:col-span-2">
    @if ($contract->type === 'milestone')
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Milestones</h2></div>
            <ul class="divide-y divide-slate-100">
                @forelse ($contract->milestones as $m)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0 flex-1"><div class="font-medium text-slate-900">{{ $m->title }}</div><div class="text-xs text-slate-500">{{ $m->due_date ? 'Due '.$m->due_date->format('d M Y') : 'No date' }}@if ($m->description) · {{ $m->description }}@endif</div></div>
                        <span class="font-medium text-slate-900">{{ money($m->amount_minor, $m->currency) }}</span>
                        <x-pill :tone="$mTone[$m->status] ?? 'slate'">{{ ucfirst($m->status) }}</x-pill>
                        @if ($isFreelancer && $contract->status === 'active' && $m->status === 'pending')
                            <form method="POST" action="{{ route('contracts.milestones.submit', [$contract, $m]) }}">@csrf<button class="btn-primary btn-sm">Mark done</button></form>
                        @endif
                        @if ($canApproveWork && $m->status === 'submitted')
                            <form method="POST" action="{{ route('contracts.milestones.approve', [$contract, $m]) }}">@csrf<button class="btn-primary btn-sm">Approve</button></form>
                            <form method="POST" action="{{ route('contracts.milestones.reopen', [$contract, $m]) }}">@csrf<button class="btn-secondary btn-sm">Send back</button></form>
                        @endif
                        @if ($canManage && $m->status === 'pending' && in_array($contract->status, ['draft', 'sent']))
                            <form method="POST" action="{{ route('contracts.milestones.delete', [$contract, $m]) }}">@csrf @method('DELETE')<button class="text-slate-300 hover:text-red-600" aria-label="Remove milestone"><i class="bi bi-x-lg"></i></button></form>
                        @endif
                    </li>
                @empty<li class="px-5 py-8 text-center text-sm text-slate-500">No milestones yet.</li>@endforelse
            </ul>
            @if ($canManage && in_array($contract->status, ['draft', 'sent']))
                <form method="POST" action="{{ route('contracts.milestones.add', $contract) }}" class="grid gap-3 border-t border-slate-100 p-4 sm:grid-cols-12">@csrf
                    <input name="title" required class="input sm:col-span-4" placeholder="Milestone" aria-label="Milestone title">
                    <input name="amount" type="number" step="0.01" min="0.01" required class="input sm:col-span-3" placeholder="Amount ({{ $contract->currency }})" aria-label="Amount">
                    <input name="due_date" type="date" class="input sm:col-span-3" aria-label="Due date">
                    <button class="btn-secondary sm:col-span-2">Add</button>
                </form>
            @endif
        </div>
    @endif

    <div class="card p-5">
        <h2 class="mb-2 font-semibold text-slate-900">Terms</h2>
        <p class="whitespace-pre-line text-sm text-slate-700">{{ $contract->terms ?: 'No additional terms were written.' }}</p>
        @if ($contract->accepted_at)<p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500"><i class="bi bi-patch-check text-emerald-500"></i> Accepted by {{ $contract->freelancer->name }} on {{ $contract->accepted_at->format('d M Y, H:i') }}</p>@endif
        @if ($contract->termination_reason)<p class="mt-4 border-t border-slate-100 pt-3 text-sm text-slate-600"><strong>Ended {{ $contract->terminated_at->format('d M Y') }}:</strong> {{ $contract->termination_reason }}</p>@endif
    </div>

    @if ($contract->invoices->isNotEmpty())
        <div class="card"><div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Invoices</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">@foreach ($contract->invoices as $inv)<li><a href="{{ route('invoices.show', $inv) }}" class="flex justify-between px-5 py-3 hover:bg-slate-50"><span class="font-medium text-slate-900">{{ $inv->number }}</span><span class="text-slate-600">{{ money($inv->total_minor, $inv->currency) }} · {{ ucfirst(str_replace('_', ' ', $inv->status)) }}</span></a></li>@endforeach</ul></div>
    @endif
</div>

<div class="space-y-6">
    @if ($canManage)
        <div class="card space-y-3 p-5">
            <h2 class="font-semibold text-slate-900">Actions</h2>
            @if ($contract->status === 'draft')
                <form method="POST" action="{{ route('contracts.send', $contract) }}">@csrf<button class="btn-primary w-full"><i class="bi bi-send"></i> Send to freelancer</button></form>
            @endif
            @if (in_array($contract->status, ['sent', 'active']))
                <form method="POST" action="{{ route('contracts.terminate', $contract) }}" class="space-y-2" onsubmit="return confirm('End this contract?')">@csrf
                    <input name="reason" required class="input" placeholder="Reason for ending" aria-label="Reason"><button class="btn-secondary w-full text-red-600">End contract</button></form>
            @endif
            @if (in_array($contract->status, ['declined', 'terminated']))<p class="text-sm text-slate-500">This contract is closed.</p>@endif
        </div>
    @endif
    <div class="card divide-y divide-slate-100 text-sm">
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Freelancer</span><span class="font-medium text-slate-900">{{ $contract->freelancer->name }}</span></div>
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Project</span><span class="font-medium text-slate-900">{{ $contract->project?->name ?? 'Any' }}</span></div>
        @if ($contract->sent_at)<div class="flex justify-between px-5 py-3"><span class="text-slate-500">Sent</span><span class="font-medium text-slate-900">{{ $contract->sent_at->format('d M Y') }}</span></div>@endif
    </div>
</div>
</div>
@endsection
