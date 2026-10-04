@extends('layouts.app')
@section('title', 'Settings')
@section('content')
@php $client = $role?->isClient(); @endphp
<x-page-title title="Settings" sub="How Freelancy works for you." />
<div class="grid max-w-4xl gap-6">
    <div class="grid gap-3 sm:grid-cols-2">
        @unless ($client)
            @can('manage-team')<a href="{{ route('settings.company') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-building text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Business profile</span><span class="text-sm text-slate-500">Name, logo, tax details, invoice defaults</span></span></a>@endcan
            <a href="{{ route('profile.edit') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-person-circle text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Your profile</span><span class="text-sm text-slate-500">Photo, address, tax numbers, signature</span></span></a>
            <a href="{{ route('settings.tax-profiles') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-percent text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Tax profiles</span><span class="text-sm text-slate-500">Saved tax settings for invoices</span></span></a>
            <a href="{{ route('recurring.index') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-arrow-repeat text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Recurring invoices</span><span class="text-sm text-slate-500">Retainers and monthly services</span></span></a>
            <a href="{{ route('settings.exchange-rates') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-currency-exchange text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Exchange rates</span><span class="text-sm text-slate-500">Rates suggested on international invoices</span></span></a>
            <a href="{{ route('settings.plan') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-stars text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Plan</span><span class="text-sm text-slate-500">What your workspace includes</span></span></a>
            @if (config('workora.platform_admin_email') && strcasecmp(config('workora.platform_admin_email'), auth()->user()->email) === 0)<a href="{{ route('admin.plans') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-gear-wide-connected text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Workspace plans (admin)</span><span class="text-sm text-slate-500">Change any workspace's plan</span></span></a>@endif
            <a href="{{ route('team.payment-profiles') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-bank text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Payment profiles</span><span class="text-sm text-slate-500">Bank, UPI, IBAN, payment links</span></span></a>
        @else
            <a href="{{ route('portal.profile') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-person-circle text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Your profile</span><span class="text-sm text-slate-500">Name and password</span></span></a>
            <a href="{{ route('portal.company') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-building text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Company</span><span class="text-sm text-slate-500">Billing details used on invoices</span></span></a>
        @endunless
        <a href="{{ route('security.index') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-shield-lock text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Security</span><span class="text-sm text-slate-500">Two-factor sign-in and signed-in devices</span></span></a>
        <a href="{{ route('notifications.preferences') }}" class="card flex items-center gap-3 p-4 hover:border-slate-300"><i class="bi bi-bell text-xl text-brand-600"></i><span><span class="block font-semibold text-slate-900">Notifications</span><span class="text-sm text-slate-500">Choose what you hear about, in the app and by email</span></span></a>
    </div>

    <form method="POST" action="{{ route('settings.locale') }}" class="card flex flex-wrap items-end gap-3 p-5">@csrf
        <div class="min-w-48 flex-1"><label class="label" for="locale">{{ __('ui.language') }}</label>
            <select id="locale" name="locale" class="input">@foreach ($locales as $code => $name)<option value="{{ $code }}" @selected($current === $code)>{{ $name }}</option>@endforeach</select>
            <p class="mt-1 text-xs text-slate-500">Menus and common labels are translated. Some screens are still in English while translations are completed.</p></div>
        <button class="btn-primary">{{ __('ui.save') }}</button>
    </form>

    @if ($solo)
    <form method="POST" action="{{ route('settings.widgets') }}" class="card space-y-3 p-5">@csrf
        <div><h2 class="font-semibold text-slate-900">Dashboard widgets</h2><p class="text-sm text-slate-500">Show only what you want on your dashboard.</p></div>
        @foreach ($widgets as $key => $label)
            <label class="flex min-h-11 items-center gap-3 text-sm text-slate-800"><input type="checkbox" name="widgets[]" value="{{ $key }}" class="size-5 rounded border-slate-300" @checked(in_array($key, $enabled, true))> {{ $label }}</label>
        @endforeach
        <button class="btn-primary">{{ __('ui.save') }}</button>
    </form>
    @endif
</div>
@endsection
