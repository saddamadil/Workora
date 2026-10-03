@extends('layouts.app')
@section('title', 'Team')
@section('content')
<x-page-title title="Team" sub="Your people, how they are paid and what they have invoiced.">
    @can('manage-team')<button type="button" class="btn-secondary" @click="$dispatch('open-invite')"><i class="bi bi-person-plus"></i> Invite</button>@endcan
    @can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> Create invoice</a>@endcan
</x-page-title>
@include('team._tabs', ['active' => 'overview'])

<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Company team" :value="$counts['staff']" icon="bi-person-badge" :href="route('team.members', ['type' => 'staff'])" />
    <x-stat label="Freelancers" :value="$counts['freelancers']" icon="bi-person-workspace" tone="green" :href="route('team.members', ['type' => 'freelancers'])" />
    <x-stat label="Invitations waiting" :value="$counts['invitations']" icon="bi-envelope-open" tone="amber" :href="route('team.invitations')" />
    <x-stat label="Without a payment profile" :value="$counts['missingPayment']" icon="bi-bank" :tone="$counts['missingPayment'] ? 'red' : 'slate'" :href="route('team.payment-profiles')" hint="Freelancers who cannot yet be paid" />
</div>

@if ($stats)
    @include('invoices._stats', ['stats' => $stats])
@endif

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Get set up</h2></div>
        <ul class="divide-y divide-slate-100">
            @foreach ($setup as [$text, $done, $href, $cta])
                <li class="flex items-center gap-3 px-5 py-3 text-sm">
                    <i class="bi {{ $done ? 'bi-check-circle-fill text-emerald-500' : 'bi-circle text-slate-300' }} text-lg"></i>
                    <span class="flex-1 {{ $done ? 'text-slate-400 line-through' : 'text-slate-800' }}">{{ $text }}</span>
                    @unless ($done)<a href="{{ $href }}" class="text-brand-600 hover:underline">{{ $cta }}</a>@endunless
                </li>
            @endforeach
        </ul>
    </div>
    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Recent invoices</h2>@if ($stats)<a href="{{ route('team.invoices') }}" class="text-xs text-brand-600 hover:underline">All invoices</a>@endif</div>
        @forelse ($invoices as $i)
            <a href="{{ route('invoices.show', $i) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                <span class="min-w-0"><span class="font-medium text-slate-900">{{ $i->number }}</span> <span class="text-slate-500">· {{ $i->freelancer->name }}</span></span>
                <span class="shrink-0 text-slate-600">{{ money($i->total_minor, $i->currency) }}</span></a>
        @empty
            <p class="px-5 py-8 text-center text-sm text-slate-500">{{ $stats ? 'No invoices yet.' : 'Invoices are visible to finance roles.' }}</p>
        @endforelse
    </div>
</div>
@can('manage-team')@include('team._invite-modal')@endcan
@endsection
