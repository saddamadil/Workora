@extends('layouts.app')
@section('title', 'Company settings')
@section('content')
<x-page-title title="Company settings" sub="Used on invoices, contracts and new projects." />
<form method="POST" action="{{ route('settings.company.update') }}" class="card max-w-3xl space-y-5 p-6">
    @csrf
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="name">Company name</label><input id="name" name="name" value="{{ old('name', $organization->name) }}" required class="input"></div>
        <div><label class="label" for="website">Website</label><input id="website" name="website" type="url" value="{{ old('website', $organization->website) }}" class="input" placeholder="https://"></div>
        <div class="sm:col-span-2"><label class="label" for="address_line1">Address</label><input id="address_line1" name="address_line1" value="{{ old('address_line1', $organization->address_line1) }}" class="input"></div>
        <div><label class="label" for="city">City</label><input id="city" name="city" value="{{ old('city', $organization->city) }}" class="input"></div>
        <div><label class="label" for="state">State</label><input id="state" name="state" value="{{ old('state', $organization->state) }}" class="input"></div>
        <div><label class="label" for="postal_code">Postal code</label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $organization->postal_code) }}" class="input"></div>
        <div><label class="label" for="country_code">Country code</label><input id="country_code" name="country_code" maxlength="2" value="{{ old('country_code', $organization->country_code) }}" class="input uppercase" placeholder="IN"></div>
        <div><label class="label" for="tax_identifier">GST / VAT number</label><input id="tax_identifier" name="tax_identifier" value="{{ old('tax_identifier', $organization->tax_identifier) }}" class="input"></div>
        <div><label class="label" for="default_tax_rate">Default tax %</label><input id="default_tax_rate" name="default_tax_rate" type="number" step="0.01" min="0" max="100" value="{{ old('default_tax_rate', $organization->default_tax_rate) }}" class="input"></div>
        <div><label class="label" for="base_currency">Main currency</label><select id="base_currency" name="base_currency" class="input">@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected(old('base_currency', $organization->base_currency) === $c)>{{ $c }}</option>@endforeach</select></div>
        <div><label class="label" for="timezone">Time zone</label><input id="timezone" name="timezone" value="{{ old('timezone', $organization->timezone) }}" class="input"></div>
    </div>
    <div class="flex justify-end"><button class="btn-primary">Save settings</button></div>
</form>
@endsection
