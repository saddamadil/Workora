@extends('layouts.app')
@section('title', 'Company profile')
@section('content')
<x-page-title title="Company profile" sub="Your invoice identity. Set it once; every invoice and contract uses it." />
<form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="card max-w-4xl space-y-6 p-6">
    @csrf
    <section class="space-y-4">
        <h2 class="font-semibold text-slate-900">Company information</h2>
        <x-image-upload name="logo" label="Company logo" :src="$organization->logo_path ? route('assets.company-logo') : null" hint="Shown on invoices and contracts. JPG, PNG or WebP, a transparent PNG looks best" />
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="name">Company name</label><input id="name" name="name" value="{{ old('name', $organization->name) }}" required class="input"></div>
            <div><label class="label" for="legal_name">Legal name</label><input id="legal_name" name="legal_name" value="{{ old('legal_name', $organization->legal_name) }}" class="input" placeholder="If different, e.g. Acme Studio Pvt Ltd"></div>
            <div><label class="label" for="email">Billing email</label><input id="email" name="email" type="email" value="{{ old('email', $organization->email) }}" class="input"></div>
            <div><label class="label" for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $organization->phone) }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label" for="website">Website</label><input id="website" name="website" type="url" value="{{ old('website', $organization->website) }}" class="input" placeholder="https://"></div>
        </div>
    </section>

    <section class="space-y-4 border-t border-slate-100 pt-6">
        <h2 class="font-semibold text-slate-900">Address and tax details</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="label" for="address_line1">Address</label><input id="address_line1" name="address_line1" value="{{ old('address_line1', $organization->address_line1) }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label" for="address_line2">Address line 2</label><input id="address_line2" name="address_line2" value="{{ old('address_line2', $organization->address_line2) }}" class="input"></div>
            <div><label class="label" for="city">City</label><input id="city" name="city" value="{{ old('city', $organization->city) }}" class="input"></div>
            <div><label class="label" for="state">State / region</label><input id="state" name="state" value="{{ old('state', $organization->state) }}" class="input"></div>
            <div><label class="label" for="postal_code">Postal code</label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $organization->postal_code) }}" class="input"></div>
        </div>
        <x-country-tax :country="$organization->country_code" :values="$organization->tax_ids ?? []" />
        @error('tax_ids.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <p class="text-xs text-slate-500">The boxes change with the country. Workora prints what you enter; it does not decide what tax applies.</p>
    </section>

    <section class="space-y-4 border-t border-slate-100 pt-6">
        <h2 class="font-semibold text-slate-900">Defaults</h2>
        <div class="grid gap-4 sm:grid-cols-4">
            <div><label class="label" for="base_currency">Main currency</label><select id="base_currency" name="base_currency" class="input">@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected(old('base_currency', $organization->base_currency) === $c)>{{ $c }}</option>@endforeach</select></div>
            <div><label class="label" for="timezone">Time zone</label><input id="timezone" name="timezone" value="{{ old('timezone', $organization->timezone) }}" class="input"></div>
            <div><label class="label" for="default_tax_rate">Default tax %</label><input id="default_tax_rate" name="default_tax_rate" type="number" step="0.01" min="0" max="100" value="{{ old('default_tax_rate', $organization->default_tax_rate) }}" class="input"></div>
            <div><label class="label" for="payment_terms_days">Pay within (days)</label><input id="payment_terms_days" name="payment_terms_days" type="number" min="0" max="180" value="{{ old('payment_terms_days', $organization->setting('payment_terms_days', 14)) }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label" for="invoice_template">Default invoice template</label>
                <select id="invoice_template" name="invoice_template" class="input">@foreach ($templates as $k => $l)<option value="{{ $k }}" @selected(old('invoice_template', $organization->setting('invoice_template', 'professional')) === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="sm:col-span-4"><label class="label" for="invoice_notes">Default note on invoices</label><input id="invoice_notes" name="invoice_notes" maxlength="2000" value="{{ old('invoice_notes', $organization->setting('invoice_notes')) }}" class="input" placeholder="e.g. Thank you for your business."></div>
        </div>
    </section>
    <div class="flex justify-end"><button class="btn-primary">Save company profile</button></div>
</form>
@endsection
