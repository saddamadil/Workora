@extends('layouts.app')
@section('title', $client->exists ? 'Edit client' : 'Add client')
@section('content')
<x-page-title :title="$client->exists ? 'Edit '.$client->name : 'Add client'" />
<form method="POST" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" enctype="multipart/form-data" class="card max-w-4xl space-y-6 p-6">
    @csrf @if ($client->exists) @method('PUT') @endif
    @if (session('duplicate'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            <strong>This client already exists.</strong> {{ session('duplicate')['name'] }} is already in your workspace.
            <a href="{{ route('clients.show', session('duplicate')['id']) }}" class="ms-1 font-semibold underline">Open existing client</a>
        </div>
    @endif
    <div x-data="{ type: '{{ old('type', $client->type ?: 'company') }}' }" class="flex flex-wrap items-center gap-4">
        <div role="radiogroup" aria-label="Client type" class="flex gap-2">
            <label class="chip cursor-pointer" :class="type === 'company' && 'chip-active'"><input type="radio" name="type" value="company" x-model="type" class="sr-only"><i class="bi bi-building"></i> Company</label>
            <label class="chip cursor-pointer" :class="type === 'individual' && 'chip-active'"><input type="radio" name="type" value="individual" x-model="type" class="sr-only"><i class="bi bi-person"></i> Individual</label>
        </div>
        @if ($client->exists)
            <label class="flex items-center gap-2 text-sm text-slate-600"><span>Status</span>
                <select name="status" class="input w-auto py-1.5"><option value="active" @selected($client->status === 'active')>Active</option><option value="inactive" @selected($client->status === 'inactive')>Inactive</option></select></label>
        @endif
    </div>
    <section class="space-y-4">
        <x-image-upload name="logo" label="Client logo" :src="$client->logo_path ? route('assets.client-logo', $client) : null" hint="Appears on invoices addressed to this client. JPG, PNG or WebP" />
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="name">Client name</label><input id="name" name="name" value="{{ old('name', $client->name) }}" required class="input"></div>
            <div><label class="label" for="legal_name">Legal name</label><input id="legal_name" name="legal_name" value="{{ old('legal_name', $client->legal_name) }}" class="input" placeholder="If different"></div>
            <div><label class="label" for="contact_name">Contact person</label><input id="contact_name" name="contact_name" value="{{ old('contact_name', $client->contact_name) }}" class="input"></div>
            <div><label class="label" for="email">Billing email</label><input id="email" name="email" type="email" value="{{ old('email', $client->email) }}" class="input"></div>
            <div><label class="label" for="website">Website</label><input id="website" name="website" type="url" value="{{ old('website', $client->website) }}" class="input" placeholder="https://"></div>
            <div><label class="label" for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $client->phone) }}" class="input"></div>
            <div><label class="label" for="default_currency">Default currency</label><select id="default_currency" name="default_currency" class="input"><option value="">Not set</option>@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected(old('default_currency', $client->default_currency) === $c)>{{ $c }}</option>@endforeach</select></div>
        </div>
    </section>
    <section class="space-y-4 border-t border-slate-100 pt-6">
        <h2 class="font-semibold text-slate-900">Billing address and tax</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="label" for="address">Billing address</label><input id="address" name="address" value="{{ old('address', $client->address) }}" class="input"></div>
            <div><label class="label" for="city">City</label><input id="city" name="city" value="{{ old('city', $client->city) }}" class="input"></div>
            <div><label class="label" for="state">State / region</label><input id="state" name="state" value="{{ old('state', $client->state) }}" class="input"></div>
            <div><label class="label" for="postal_code">Postal code</label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}" class="input"></div>
        </div>
        <x-country-tax :country="$client->country_code" :values="$client->tax_ids ?? []" />
        @error('tax_ids.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <div><label class="label" for="payment_method">Usual payment method</label><input id="payment_method" name="payment_method" value="{{ old('payment_method', $client->payment_method) }}" class="input" placeholder="e.g. Bank transfer, Wise, PayPal"></div>
        <div><label class="label" for="notes">Private notes <span class="font-normal text-slate-400">(only you can see these)</span></label><textarea id="notes" name="notes" rows="2" class="input">{{ old('notes', $client->notes) }}</textarea></div>
    </section>
    <div class="flex justify-between gap-2">
        @if ($client->exists)<button type="submit" form="del" class="btn-secondary text-red-600"><i class="bi bi-trash"></i> Remove</button>@else <span></span> @endif
        <div class="flex gap-2"><a href="{{ $client->exists ? route('clients.show', $client) : route('clients.index') }}" class="btn-secondary">Cancel</a><button class="btn-primary">Save client</button></div>
    </div>
</form>
@if ($client->exists)
    <form id="del" method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('Remove this client? Projects and past invoices are kept.')" class="hidden">@csrf @method('DELETE')</form>
@endif
@endsection
