@extends('layouts.app')
@section('title', 'Your profile')
@section('content')
<x-page-title title="Your profile" :sub="$user->email" />
<div class="grid max-w-5xl gap-6 lg:grid-cols-3">
<form method="POST" action="{{ route('profile.update') }}" class="card space-y-5 p-6 lg:col-span-2">
    @csrf
    <h2 class="font-semibold text-slate-900">About you</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" required class="input"></div>
        <div><label class="label" for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="input"></div>
        <div class="sm:col-span-2"><label class="label" for="timezone">Time zone</label>
            <select id="timezone" name="timezone" class="input">@foreach ($timezones as $tz)<option @selected(old('timezone', $user->timezone) === $tz)>{{ $tz }}</option>@endforeach</select></div>
    </div>

    @if ($profile)
        <h2 class="border-t border-slate-100 pt-5 font-semibold text-slate-900">Freelancer profile</h2>
        <div><label class="label" for="headline">Headline</label><input id="headline" name="headline" value="{{ old('headline', $profile->headline) }}" class="input" placeholder="e.g. Laravel developer, 6 years"></div>
        <div><label class="label" for="bio">Bio</label><textarea id="bio" name="bio" rows="3" class="input">{{ old('bio', $profile->bio) }}</textarea></div>
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2"><label class="label" for="hourly_rate">Usual hourly rate</label><input id="hourly_rate" name="hourly_rate" type="number" step="0.01" min="0" value="{{ old('hourly_rate', \App\Support\Money::toInput($profile->default_hourly_rate_minor)) }}" class="input"></div>
            <div><label class="label" for="default_currency">Currency</label><select id="default_currency" name="default_currency" class="input">@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected(old('default_currency', $profile->default_currency) === $c)>{{ $c }}</option>@endforeach</select></div>
            <div><label class="label" for="years_experience">Years</label><input id="years_experience" name="years_experience" type="number" step="0.5" min="0" value="{{ old('years_experience', $profile->years_experience) }}" class="input"></div>
            <div class="sm:col-span-2"><label class="label" for="availability">Availability</label><select id="availability" name="availability" class="input">@foreach (['available' => 'Available', 'limited' => 'Limited', 'unavailable' => 'Not available'] as $k => $l)<option value="{{ $k }}" @selected(old('availability', $profile->availability) === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="sm:col-span-2"><label class="label" for="portfolio_url">Portfolio link</label><input id="portfolio_url" name="portfolio_url" type="url" value="{{ old('portfolio_url', $profile->portfolio_url) }}" class="input" placeholder="https://"></div>
        </div>

        <h2 class="border-t border-slate-100 pt-5 font-semibold text-slate-900">How you get paid</h2>
        <p class="-mt-3 text-sm text-slate-500">Shown to the people who pay you. Use a UPI ID, a PayPal email or a masked account such as "HDFC ending 1234". Never enter a card number, PIN or password.</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="label" for="payout_type">Method</label><select id="payout_type" name="payout_type" class="input">@foreach (['bank_transfer' => 'Bank transfer', 'upi' => 'UPI', 'paypal' => 'PayPal', 'wise' => 'Wise', 'other' => 'Other'] as $k => $l)<option value="{{ $k }}" @selected(old('payout_type', $payout?->type) === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="sm:col-span-2"><label class="label" for="payout_label">Payment ID</label><input id="payout_label" name="payout_label" value="{{ old('payout_label', $payout?->label) }}" class="input" placeholder="you@okhdfc"></div>
            <div class="sm:col-span-3"><label class="label" for="payout_notes">Instructions <span class="font-normal text-slate-400">(optional)</span></label><input id="payout_notes" name="payout_notes" value="{{ old('payout_notes', $payout?->details_encrypted['notes'] ?? '') }}" class="input"></div>
        </div>
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
