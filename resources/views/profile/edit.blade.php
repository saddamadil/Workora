@extends('layouts.app')
@section('title', 'Your profile')
@section('content')
<x-page-title title="Your profile" :sub="$user->email">
    @if ($profile && ($org ?? null))<a href="{{ route('team.payment-profiles') }}" class="btn-secondary"><i class="bi bi-bank"></i> Payment profiles</a>@endif
</x-page-title>
<div class="grid max-w-5xl gap-6 lg:grid-cols-3">
<form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card space-y-6 p-6 lg:col-span-2">
    @csrf
    <section class="space-y-4">
        <h2 class="font-semibold text-slate-900">About you</h2>
        <x-image-upload name="photo" label="Profile photo" shape="round" :src="$user->avatar_path ? route('assets.avatar', $user) : null" hint="Shown on your invoices. JPG, PNG or WebP" />
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="name">Full name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input"></div>
            <div><label class="label" for="email">Email</label><input id="email" value="{{ $user->email }}" disabled class="input bg-slate-50 text-slate-500"></div>
            <div><label class="label" for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="input"></div>
            <div><label class="label" for="timezone">Time zone</label>
                <select id="timezone" name="timezone" class="input">@foreach ($timezones as $tz)<option @selected(old('timezone', $user->timezone) === $tz)>{{ $tz }}</option>@endforeach</select></div>
        </div>
    </section>

    @if ($profile)
        <section class="space-y-4 border-t border-slate-100 pt-6">
            <div class="flex items-center justify-between"><h2 class="font-semibold text-slate-900">Professional</h2>
                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs text-slate-600">Freelancer ID <strong class="font-mono">{{ $profile->freelancer_code }}</strong></span></div>
            <div><label class="label" for="headline">Professional title</label><input id="headline" name="headline" value="{{ old('headline', $profile->headline) }}" class="input" placeholder="e.g. Digital marketing consultant"></div>
            <div><label class="label" for="bio">Bio</label><textarea id="bio" name="bio" rows="3" class="input">{{ old('bio', $profile->bio) }}</textarea></div>
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="sm:col-span-2"><label class="label" for="hourly_rate">Usual hourly rate</label><input id="hourly_rate" name="hourly_rate" type="number" step="0.01" min="0" value="{{ old('hourly_rate', \App\Support\Money::toInput($profile->default_hourly_rate_minor)) }}" class="input"></div>
                <div><label class="label" for="default_currency">Payment currency</label><select id="default_currency" name="default_currency" class="input">@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected(old('default_currency', $profile->default_currency) === $c)>{{ $c }}</option>@endforeach</select></div>
                <div><label class="label" for="years_experience">Years</label><input id="years_experience" name="years_experience" type="number" step="0.5" min="0" value="{{ old('years_experience', $profile->years_experience) }}" class="input"></div>
                <div class="sm:col-span-2"><label class="label" for="availability">Availability</label><select id="availability" name="availability" class="input">@foreach (['available' => 'Available', 'limited' => 'Limited', 'unavailable' => 'Not available'] as $k => $l)<option value="{{ $k }}" @selected(old('availability', $profile->availability) === $k)>{{ $l }}</option>@endforeach</select></div>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label class="label" for="website">Website</label><input id="website" name="website" type="url" value="{{ old('website', $profile->website) }}" class="input" placeholder="https://"></div>
                <div><label class="label" for="linkedin_url">LinkedIn</label><input id="linkedin_url" name="linkedin_url" type="url" value="{{ old('linkedin_url', $profile->linkedin_url) }}" class="input" placeholder="https://linkedin.com/in/…"></div>
                <div><label class="label" for="portfolio_url">Portfolio</label><input id="portfolio_url" name="portfolio_url" type="url" value="{{ old('portfolio_url', $profile->portfolio_url) }}" class="input" placeholder="https://"></div>
            </div>
        </section>

        <section class="space-y-4 border-t border-slate-100 pt-6">
            <h2 class="font-semibold text-slate-900">Address and tax details</h2>
            <p class="-mt-2 text-sm text-slate-500">Printed on your invoices as the "From" block. Which tax numbers you need depends on your country and whether you are registered; ask your accountant if unsure.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="label" for="address_line1">Address</label><input id="address_line1" name="address_line1" value="{{ old('address_line1', $profile->address_line1) }}" class="input"></div>
                <div><label class="label" for="city">City</label><input id="city" name="city" value="{{ old('city', $profile->city) }}" class="input"></div>
                <div><label class="label" for="state">State / region</label><input id="state" name="state" value="{{ old('state', $profile->state) }}" class="input"></div>
                <div><label class="label" for="postal_code">Postal code</label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $profile->postal_code) }}" class="input"></div>
            </div>
            <x-country-tax :country="$profile->country_code" :values="$profile->tax_ids ?? []" />
            @error('tax_ids.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </section>

        <section class="space-y-4 border-t border-slate-100 pt-6">
            <h2 class="font-semibold text-slate-900">Invoice identity</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="label" for="invoice_prefix">Invoice number prefix</label><input id="invoice_prefix" name="invoice_prefix" maxlength="6" value="{{ old('invoice_prefix', $profile->invoice_prefix) }}" class="input uppercase" placeholder="INV">
                    <p class="mt-1 text-xs text-slate-400">Numbers run INV-2026-001, INV-2026-002… and restart each financial year.</p></div>
            </div>
            <x-image-upload name="signature" label="Signature" :src="$profile->signature_path ? route('profile.signature') : null" hint="A photo or scan of your signature on white or transparent" />
        </section>
    @endif
    <div class="flex justify-end"><button class="btn-primary">Save profile</button></div>
</form>

<form method="POST" action="{{ route('profile.password') }}" class="card h-fit space-y-4 p-6">
    @csrf
    <h2 class="font-semibold text-slate-900">Change password</h2>
    <div><label class="label" for="current_password">Current password</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="input"></div>
    <div><label class="label" for="new_password">New password</label><input id="new_password" type="password" name="password" required minlength="8" autocomplete="new-password" class="input"></div>
    <div><label class="label" for="new_password_confirmation">Repeat it</label><input id="new_password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="input"></div>
    <button class="btn-secondary w-full">Change password</button>
</form>
</div>
@endsection
