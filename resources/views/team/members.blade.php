@extends('layouts.app')
@section('title', 'Team members')
@section('content')
@php $canManage = Gate::allows('manage-team'); $money = Gate::allows('see-money'); @endphp
<x-page-title title="Team" sub="Everyone who works with {{ $org->name }}.">
    @if ($canManage)<button type="button" class="btn-primary" @click="$dispatch('open-invite')"><i class="bi bi-person-plus"></i> Invite someone</button>@endif
</x-page-title>
@include('team._tabs', ['active' => 'members'])

<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
    <div class="flex gap-2">
        @foreach ([null => 'Everyone', 'freelancers' => 'Freelancers', 'staff' => 'Company team'] as $key => $label)
            <a href="{{ route('team.members', $key ? ['type' => $key] : []) }}" class="chip {{ ($type ?: null) === $key ? 'chip-active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
    <form method="GET" class="relative sm:ms-auto sm:w-64">@if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
        <i class="bi bi-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="search" name="q" value="{{ $search }}" placeholder="Search people" class="input pl-10" aria-label="Search people"></form>
</div>

@if ($members->isEmpty())
    <div class="card px-6 py-12 text-center"><i class="bi bi-people text-4xl text-slate-300"></i><p class="mt-3 font-semibold text-slate-700">No one matches.</p></div>
@else
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($members as $m)
        @php
            $u = $m->user; $p = $u->freelancerProfile; $fl = $m->isFreelancer();
            $country = $p?->country_code ?: $u->country_code;
            $currency = $m->default_rate_currency ?: ($p?->default_currency ?: $org->base_currency);
            $rate = $m->default_rate_minor ?: $p?->default_hourly_rate_minor;
        @endphp
        <article class="card flex flex-col p-5" x-data="{ edit: false }" @edit-member-{{ $m->id }}.window="edit = true">
            <div class="flex items-start gap-3">
                <x-avatar :user="$u" size="size-12" />
                <div class="min-w-0 flex-1">
                    <div class="truncate font-semibold text-slate-900">{{ $u->name }} @if ($u->id === auth()->id())<span class="text-xs font-normal text-slate-400">(you)</span>@endif</div>
                    <div class="truncate text-sm text-slate-500">{{ $fl ? ($p?->headline ?: 'Freelancer') : $m->role->label() }}</div>
                </div>
                <x-pill :tone="$m->status === 'active' ? 'green' : 'amber'">{{ $m->status === 'active' ? 'Active' : ucfirst(str_replace('_', ' ', $m->status)) }}</x-pill>
            </div>
            <dl class="mt-4 space-y-1.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Email</dt><dd class="truncate font-medium text-slate-800">{{ $u->email }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500">Country</dt><dd class="font-medium text-slate-800">{{ \App\Support\Countries::name($country) ?: '—' }}</dd></div>
                @if ($fl)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Currency</dt><dd class="font-medium text-slate-800">{{ $currency }}</dd></div>
                    @if ($money)<div class="flex justify-between gap-3"><dt class="text-slate-500">Rate</dt><dd class="font-medium text-slate-800">{{ $rate ? money($rate, $currency).'/hr' : '—' }}</dd></div>@endif
                    <div class="flex items-center justify-between gap-3"><dt class="text-slate-500">Payment</dt>
                        <dd class="flex flex-wrap justify-end gap-1.5">
                            <x-pill :tone="$profiles->has($m->user_id) ? 'green' : 'red'">{{ $profiles->has($m->user_id) ? 'Profile ready' : 'No payment profile' }}</x-pill>
                            @if ($money && ($owed[$m->user_id] ?? 0) > 0)<x-pill tone="amber">{{ money((int) $owed[$m->user_id], $org->base_currency) }} owed</x-pill>@endif
                        </dd></div>
                @else
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Role</dt><dd class="font-medium text-slate-800">{{ $m->role->label() }}</dd></div>
                @endif
            </dl>
            <div class="mt-4 flex items-center gap-2">
                <a href="{{ route('team.member', $m) }}" class="btn-secondary btn-sm flex-1">View profile</a>
                @if ($fl)@can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create', ['freelancer' => $m->user_id]) }}" class="btn-primary btn-sm flex-1"><i class="bi bi-receipt"></i> Create invoice</a>@endcan @endif
                @if ($canManage)
                    <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                        <button type="button" @click="open = !open" class="btn-secondary btn-sm" aria-label="More actions for {{ $u->name }}" :aria-expanded="open"><i class="bi bi-three-dots"></i></button>
                        <div x-show="open" x-cloak @click.outside="open = false" class="absolute bottom-full right-0 z-20 mb-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                            <button type="button" class="menu-item" @click="$dispatch('edit-member-{{ $m->id }}'); open = false"><i class="bi bi-pencil"></i> {{ $fl ? 'Edit rate and status' : 'Edit role and status' }}</button>
                            @if ($m->user_id !== auth()->id())
                                <form method="POST" action="{{ route('team.update', $m->id) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $m->status === 'active' ? 'inactive' : 'active' }}">
                                    <button class="menu-item {{ $m->status === 'active' ? 'text-red-600' : '' }}"><i class="bi {{ $m->status === 'active' ? 'bi-person-dash' : 'bi-person-check' }}"></i> {{ $m->status === 'active' ? 'Make inactive' : 'Reactivate' }}</button></form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
            @if ($canManage)
                <form method="POST" action="{{ route('team.update', $m->id) }}" x-show="edit" x-cloak class="mt-3 space-y-3 rounded-xl bg-slate-50 p-4">
                    @csrf @method('PATCH')
                    @unless ($fl)<div><label class="label">Role</label><select name="role" class="input">@foreach ($roles as $r)<option value="{{ $r->value }}" @selected($m->role === $r)>{{ $r->label() }}</option>@endforeach</select></div>@endunless
                    <div><label class="label">Status</label><select name="status" class="input">@foreach (['active' => 'Active', 'on_hold' => 'On hold', 'inactive' => 'Inactive'] as $k => $l)<option value="{{ $k }}" @selected($m->status === $k)>{{ $l }}</option>@endforeach</select></div>
                    @if ($fl)<div class="grid grid-cols-3 gap-2"><div class="col-span-2"><label class="label">Default hourly rate</label><input name="default_rate" type="number" step="0.01" min="0" value="{{ \App\Support\Money::toInput($m->default_rate_minor) }}" class="input"></div>
                        <div><label class="label">Currency</label><select name="default_rate_currency" class="input">@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected($currency === $c)>{{ $c }}</option>@endforeach</select></div></div>@endif
                    <div class="flex gap-2"><button type="button" class="btn-secondary btn-sm" @click="edit = false">Cancel</button><button class="btn-primary btn-sm flex-1">Save</button></div>
                </form>
            @endif
        </article>
    @endforeach
</div>
@endif
@if ($canManage)@include('team._invite-modal')@endif
@endsection
