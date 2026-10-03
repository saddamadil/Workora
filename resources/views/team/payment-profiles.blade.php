@extends('layouts.app')
@section('title', 'Payment profiles')
@section('content')
<x-page-title title="Team" :sub="$isFreelancer ? 'Where you want to be paid. Saved once, used on every invoice.' : 'Where each freelancer is paid. Details are masked here and shown in full on their invoices.'" />
@include('team._tabs', ['active' => 'payments'])

@if ($isFreelancer)
    <div class="mb-6 flex gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <i class="bi bi-shield-lock mt-0.5 text-lg"></i>
        <p><strong>Only enter details someone needs to send you money</strong>: account number and IFSC, IBAN and SWIFT, a UPI ID or a payment link. Never enter a password, PIN, OTP, card number or CVV. Details are stored encrypted and shown masked in lists.</p>
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-2">
        @forelse ($mine as $p)
            <article class="card p-5" x-data="{ edit: false }">
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-slate-100 text-xl text-slate-600"><i class="bi {{ $p->isInternational() ? 'bi-globe2' : 'bi-bank' }}"></i></span>
                    <div class="min-w-0 flex-1"><div class="truncate font-semibold text-slate-900">{{ $p->label }}</div><div class="truncate text-sm text-slate-500">{{ $p->summary() }}</div></div>
                    <div class="flex flex-col items-end gap-1"><x-pill>{{ $p->isInternational() ? 'International' : 'India' }}</x-pill><x-pill tone="blue">{{ $p->currency }}</x-pill></div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    @if ($p->is_default)<x-pill tone="green">Default for {{ $p->isInternational() ? 'international' : 'India' }} invoices</x-pill>
                    @else<form method="POST" action="{{ route('team.payment-profiles.default', $p) }}">@csrf<button class="btn-secondary btn-sm">Make default</button></form>@endif
                    <button type="button" class="btn-secondary btn-sm ms-auto" @click="edit = !edit"><i class="bi bi-pencil"></i> Edit</button>
                    <form method="POST" action="{{ route('team.payment-profiles.destroy', $p) }}" onsubmit="return confirm('Remove this payment profile?')">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600" aria-label="Remove {{ $p->label }}"><i class="bi bi-trash"></i></button></form>
                </div>
                <div x-show="edit" x-cloak class="mt-4 border-t border-slate-100 pt-4">@include('team._payment-form', ['profile' => $p])</div>
            </article>
        @empty
            <div class="card px-6 py-10 text-center lg:col-span-2"><i class="bi bi-bank text-4xl text-slate-300"></i><p class="mt-3 font-semibold text-slate-700">You have no payment profile yet.</p><p class="text-sm text-slate-500">Add one below so invoices can show how to pay you.</p></div>
        @endforelse
    </div>

    <div class="card max-w-3xl p-6"><h2 class="mb-4 font-semibold text-slate-900">Add a payment profile</h2>@include('team._payment-form', ['profile' => null])</div>
@else
    <div class="card divide-y divide-slate-100">
        @forelse ($freelancers as $m)
            <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                <x-avatar :user="$m->user" />
                <div class="min-w-0 flex-1"><a href="{{ route('team.member', $m) }}" class="font-semibold text-slate-900 hover:text-brand-600">{{ $m->user->name }}</a><div class="truncate text-sm text-slate-500">{{ $m->user->email }}</div></div>
                <div class="flex flex-wrap justify-end gap-2">
                    @forelse ($m->profiles as $p)<span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-700"><i class="bi {{ $p->isInternational() ? 'bi-globe2' : 'bi-bank' }}"></i> {{ $p->summary() }} · {{ $p->currency }}</span>
                    @empty<x-pill tone="red">No payment profile</x-pill>@endforelse
                </div>
            </div>
        @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No freelancers yet.</p>@endforelse
    </div>
@endif
@endsection
