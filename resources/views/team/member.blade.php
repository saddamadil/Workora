@extends('layouts.app')
@section('title', $member->user->name)
@section('content')
@php $u = $member->user; $fl = $member->isFreelancer(); @endphp
<div class="mb-2 text-sm"><a href="{{ route('team.members') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Team</a></div>
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
    <x-avatar :user="$u" size="size-20" />
    <div class="min-w-0 flex-1">
        <h1 class="truncate text-2xl font-bold text-slate-900">{{ $u->name }}</h1>
        <p class="text-slate-500">{{ $fl ? ($profile?->headline ?: 'Freelancer') : $member->role->label() }}@if ($fl && $profile?->freelancer_code) · <span class="font-mono text-xs">{{ $profile->freelancer_code }}</span>@endif</p>
        <div class="mt-1 flex gap-2"><x-pill :tone="$member->status === 'active' ? 'green' : 'amber'">{{ ucfirst(str_replace('_', ' ', $member->status)) }}</x-pill><x-pill :tone="$fl ? 'indigo' : 'blue'">{{ $member->role->label() }}</x-pill></div>
    </div>
    @if ($fl)@can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create', ['freelancer' => $member->user_id]) }}" class="btn-primary"><i class="bi bi-receipt"></i> Create invoice</a>@endcan @endif
</div>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-900">Contact</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="truncate font-medium text-slate-800">{{ $u->email }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Phone</dt><dd class="font-medium text-slate-800">{{ $u->phone ?: '—' }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-slate-500">Country</dt><dd class="font-medium text-slate-800">{{ \App\Support\Countries::name($profile?->country_code ?: $u->country_code) ?: '—' }}</dd></div>
            @if ($canSeeDetails && $profile)
                <div class="border-t border-slate-100 pt-2"><dt class="text-slate-500">Address</dt><dd class="mt-1 font-medium text-slate-800">{{ collect([$profile->address_line1, $profile->city, $profile->state, $profile->postal_code])->filter()->join(', ') ?: '—' }}</dd></div>
                @foreach (\App\Support\TaxFields::lines($profile->country_code, $profile->tax_ids) as [$l, $v])<div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $l }}</dt><dd class="font-mono text-slate-800">{{ $v }}</dd></div>@endforeach
            @endif
        </dl>
    </div>
    <div class="space-y-6 lg:col-span-2">
        @if ($fl && $profile)
            <div class="card p-5">
                <h2 class="mb-3 font-semibold text-slate-900">Professional</h2>
                @if ($profile->bio)<p class="mb-3 whitespace-pre-line text-sm text-slate-700">{{ $profile->bio }}</p>@endif
                <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Availability</dt><dd class="font-medium text-slate-800">{{ ucfirst($profile->availability) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Payment currency</dt><dd class="font-medium text-slate-800">{{ $profile->default_currency }}</dd></div>
                    @foreach ([['Website', $profile->website], ['LinkedIn', $profile->linkedin_url], ['Portfolio', $profile->portfolio_url]] as [$l, $v])
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $l }}</dt><dd class="truncate font-medium">@if ($v)<a href="{{ $v }}" target="_blank" rel="noopener noreferrer" class="text-brand-600 hover:underline">{{ preg_replace('#^https?://(www\.)?#', '', $v) }}</a>@else — @endif</dd></div>
                    @endforeach
                </dl>
            </div>
        @endif
        @can('see-money')
            @if ($fl)
                <div class="card">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Payment profiles</h2><a href="{{ route('team.payment-profiles') }}" class="text-xs text-brand-600 hover:underline">All profiles</a></div>
                    @forelse ($payment as $pm)
                        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
                            <i class="bi {{ $pm->isInternational() ? 'bi-globe2' : 'bi-bank' }} text-lg text-slate-400"></i>
                            <div class="min-w-0 flex-1"><div class="font-medium text-slate-900">{{ $pm->label }} @if ($pm->is_default)<x-pill tone="green">Default</x-pill>@endif</div><div class="truncate text-xs text-slate-500">{{ $pm->summary() }} · {{ $pm->currency }}</div></div>
                            <x-pill>{{ $pm->isInternational() ? 'International' : 'India' }}</x-pill></div>
                    @empty<p class="px-5 py-6 text-center text-sm text-slate-500">{{ $u->name }} has not added a payment profile yet.</p>@endforelse
                </div>
                <div class="card">
                    <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Recent invoices</h2></div>
                    @forelse ($invoices as $i)
                        <a href="{{ route('invoices.show', $i) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50"><span class="font-medium text-slate-900">{{ $i->number }}</span><span class="text-slate-600">{{ money($i->total_minor, $i->currency) }} · {{ ucfirst($i->displayStatus()) }}</span></a>
                    @empty<p class="px-5 py-6 text-center text-sm text-slate-500">No invoices yet.</p>@endforelse
                </div>
            @endif
        @endcan
    </div>
</div>
@endsection
