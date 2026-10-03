@extends('layouts.app')
@section('title', 'Company')
@section('content')
<x-page-title title="Team" sub="The companies you work with." />
@include('team._tabs', ['active' => 'members'])
<div class="grid gap-4 sm:grid-cols-2">
    @foreach ($companies as $c)
        @php $isCurrent = $c->id === $current->id; @endphp
        <article class="card p-5">
            <div class="flex items-start gap-3">
                <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 text-2xl text-slate-400">
                    @if ($isCurrent && $c->logo_path)<img src="{{ route('assets.company-logo') }}" alt="" class="size-full object-contain">@else<i class="bi bi-building"></i>@endif</span>
                <div class="min-w-0 flex-1"><div class="truncate font-semibold text-slate-900">{{ $c->documentName() }}</div><div class="truncate text-sm text-slate-500">{{ $c->email ?: $c->website ?: ' ' }}</div></div>
                @if ($isCurrent)<x-pill tone="green">Current</x-pill>@endif
            </div>
            <dl class="mt-4 space-y-1.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Country</dt><dd class="font-medium text-slate-800">{{ \App\Support\Countries::name($c->country_code) ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Billing address</dt><dd class="truncate text-right font-medium text-slate-800">{{ collect([$c->address_line1, $c->city, $c->state])->filter()->join(', ') ?: '—' }}</dd></div>
                @foreach (\App\Support\TaxFields::lines($c->country_code, $c->tax_ids) as [$l, $v])<div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $l }}</dt><dd class="font-mono text-slate-800">{{ $v }}</dd></div>@endforeach
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Default currency</dt><dd class="font-medium text-slate-800">{{ $c->base_currency }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Payment terms</dt><dd class="font-medium text-slate-800">Net {{ $c->setting('payment_terms_days', 14) }}</dd></div>
            </dl>
            <div class="mt-4 flex gap-2">
                @if ($isCurrent)<a href="{{ route('invoices.create') }}" class="btn-primary btn-sm flex-1"><i class="bi bi-receipt"></i> Create invoice</a>
                @else<form method="POST" action="{{ route('organizations.switch', $c->slug) }}" class="flex-1">@csrf<button class="btn-secondary btn-sm w-full">Switch to this company</button></form>@endif
            </div>
        </article>
    @endforeach
</div>
@endsection
