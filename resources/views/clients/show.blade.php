@extends('layouts.app')
@section('title', $client->name)
@section('content')
<div class="mb-2 text-sm"><a href="{{ route('clients.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Clients</a></div>
<x-page-title :title="$client->name" :sub="$client->legal_name ?: ($client->contact_name ?: null)">
    @can('manage-clients')<a href="{{ route('clients.edit', $client) }}" class="btn-secondary"><i class="bi bi-pencil"></i> Edit</a>@endcan
    @can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create', ['client' => $client->id]) }}" class="btn-primary"><i class="bi bi-receipt"></i> Create invoice</a>@endcan
</x-page-title>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-5 lg:col-span-1">
        <div class="mb-4 grid h-24 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50 text-4xl text-slate-300">
            @if ($client->logo_path)<img src="{{ route('assets.client-logo', $client) }}" alt="" class="max-h-20 object-contain">@else<i class="bi bi-building"></i>@endif</div>
        <dl class="space-y-2 text-sm">
            @foreach ([['Contact', $client->contact_name], ['Email', $client->email], ['Phone', $client->phone], ['Currency', $client->default_currency], ['Payment method', $client->payment_method]] as [$l, $v])
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $l }}</dt><dd class="truncate text-right font-medium text-slate-800">{{ $v ?: '—' }}</dd></div>
            @endforeach
            <div class="border-t border-slate-100 pt-2"><dt class="text-slate-500">Billing address</dt>
                <dd class="mt-1 font-medium text-slate-800">{{ collect([$client->address, $client->city, $client->state, $client->postal_code, \App\Support\Countries::name($client->country_code)])->filter()->join(', ') ?: '—' }}</dd></div>
            @foreach (\App\Support\TaxFields::lines($client->country_code, $client->tax_ids) as [$l, $v])
                <div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $l }}</dt><dd class="font-mono text-slate-800">{{ $v }}</dd></div>
            @endforeach
        </dl>
    </div>
    <div class="space-y-6 lg:col-span-2">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Invoices to this client</h2></div>
            @forelse ($invoices as $i)
                <a href="{{ route('invoices.show', $i) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                    <span class="font-medium text-slate-900">{{ $i->number }} <span class="font-normal text-slate-500">· {{ $i->freelancer->name }}</span></span>
                    <span class="text-slate-600">{{ money($i->total_minor, $i->currency) }} · {{ ucfirst($i->displayStatus()) }}</span></a>
            @empty<p class="px-5 py-8 text-center text-sm text-slate-500">No invoices yet.</p>@endforelse
        </div>
        @if ($client->notes)<p class="card whitespace-pre-line p-5 text-sm text-slate-700">{{ $client->notes }}</p>@endif
    </div>
</div>
@endsection
