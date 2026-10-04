@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $fmt = fn (array $by) => $by ? collect($by)->map(fn ($m, $c) => money($m, $c))->implode(' + ') : null;
    $first = explode(' ', $freelancer)[0];
@endphp
<x-page-title :title="'Welcome, '.$client->name" :sub="'Your projects and invoices with '.$freelancer.'.'" />

<div class="mb-6 flex flex-wrap gap-2" aria-label="Quick actions">
    <span class="btn-secondary cursor-not-allowed opacity-60" title="Coming soon" aria-disabled="true"><i class="bi bi-plus-circle"></i> Request work <span class="text-[10px] uppercase">Soon</span></span>
    <span class="btn-secondary cursor-not-allowed opacity-60" title="Coming soon" aria-disabled="true"><i class="bi bi-chat-dots"></i> Message {{ $first }} <span class="text-[10px] uppercase">Soon</span></span>
    <a href="{{ route('portal.invoices') }}" class="btn-secondary"><i class="bi bi-receipt"></i> View invoices</a>
    <span class="btn-secondary cursor-not-allowed opacity-60" title="Coming soon" aria-disabled="true"><i class="bi bi-cloud-arrow-up"></i> Upload file <span class="text-[10px] uppercase">Soon</span></span>
</div>

<div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Active projects" :value="$active" icon="bi-kanban" tone="slate" :href="route('portal.projects')" />
    <x-stat label="Pending tasks" :value="$pendingTasks" icon="bi-check2-square" tone="slate" />
    <x-stat label="Invoices due" :value="$invoicesDue" icon="bi-receipt" :tone="$invoicesDue ? 'amber' : 'slate'" :hint="$fmt($dueTotals)" :href="route('portal.invoices')" />
    <x-stat label="Completed projects" :value="$completed" icon="bi-check2-circle" tone="green" />
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <section class="space-y-3 lg:col-span-2" aria-labelledby="cur">
        <h2 id="cur" class="font-semibold text-slate-900">Current projects</h2>
        @forelse ($projects as $p) @include('portal._project-card', ['p' => $p])
        @empty
            <div class="card px-6 py-10 text-center text-sm text-slate-500"><i class="bi bi-kanban text-3xl text-slate-300"></i><p class="mt-2">No active projects yet. {{ $freelancer }} will add your projects here.</p></div>
        @endforelse
    </section>
    <div class="space-y-6">
        <section class="card" aria-labelledby="up">
            <div class="border-b border-slate-100 px-5 py-3"><h2 id="up" class="font-semibold text-slate-900">Upcoming</h2></div>
            @foreach ($upcoming['invoices'] as $i)
                <a href="{{ route('portal.invoice', $i) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm hover:bg-slate-50"><i class="bi bi-receipt text-slate-400"></i><span class="min-w-0 flex-1 truncate">Invoice {{ $i->number }} due</span><span class="text-slate-500">{{ $i->due_date->format('d M') }}</span></a>
            @endforeach
            @foreach ($upcoming['deadlines'] as $p)
                <a href="{{ route('portal.project', $p->slug) }}" class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50"><i class="bi bi-flag text-slate-400"></i><span class="min-w-0 flex-1 truncate">{{ $p->name }} deadline</span><span class="text-slate-500">{{ $p->deadline->format('d M') }}</span></a>
            @endforeach
            @if ($upcoming['invoices']->isEmpty() && $upcoming['deadlines']->isEmpty())<p class="px-5 py-8 text-center text-sm text-slate-500">Nothing coming up in the next 30 days.</p>@endif
        </section>
        <section class="card" aria-labelledby="ri">
            <div class="border-b border-slate-100 px-5 py-3"><h2 id="ri" class="font-semibold text-slate-900">Recent invoices</h2></div>
            @forelse ($recentInvoices as $i)
                <a href="{{ route('portal.invoice', $i) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50"><span class="font-medium text-slate-900">{{ $i->number }}</span><span class="text-slate-600">{{ money($i->total_minor, $i->currency) }}</span></a>
            @empty<p class="px-5 py-8 text-center text-sm text-slate-500">No invoices yet.</p>@endforelse
        </section>
    </div>
</div>
@endsection
