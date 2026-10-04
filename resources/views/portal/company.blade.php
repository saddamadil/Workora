@extends('layouts.app')
@section('title', 'Company')
@section('content')
<x-page-title title="Company" sub="Your company details. They are filled in automatically on your invoices." />
<form method="POST" action="{{ route('portal.company.update') }}" enctype="multipart/form-data" class="card max-w-4xl space-y-6 p-6">@csrf
    <section class="space-y-4">
        <x-image-upload name="logo" label="Company logo" :src="$client->logo_path ? route('assets.client-logo', $client) : null" hint="Appears on invoices addressed to you. JPG, PNG or WebP" />
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="name">Company name</label><input id="name" name="name" value="{{ old('name', $client->name) }}" required class="input">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="legal_name">Legal name</label><input id="legal_name" name="legal_name" value="{{ old('legal_name', $client->legal_name) }}" class="input"></div>
            <div><label class="label" for="contact_name">Contact person</label><input id="contact_name" name="contact_name" value="{{ old('contact_name', $client->contact_name) }}" class="input"></div>
            <div><label class="label" for="email">Billing email</label><input id="email" name="email" type="email" value="{{ old('email', $client->email) }}" class="input">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $client->phone) }}" class="input"></div>
            <div><label class="label" for="website">Website</label><input id="website" name="website" type="url" value="{{ old('website', $client->website) }}" class="input"></div>
            <div><label class="label" for="default_currency">Preferred currency</label><select id="default_currency" name="default_currency" class="input"><option value="">Not set</option>@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected(old('default_currency', $client->default_currency) === $c)>{{ $c }}</option>@endforeach</select></div>
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
    </section>
    <div class="flex justify-end"><button class="btn-primary">Save company details</button></div>
</form>
@endsection
