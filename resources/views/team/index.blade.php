@extends('layouts.app')
@section('title', 'Team & freelancers')
@section('content')
@php $canManage = Gate::allows('manage-team'); @endphp
<x-page-title title="Team & freelancers" sub="Everyone who works with {{ $org->name }}.">
    @if ($canManage)<button type="button" class="btn-primary" @click="$dispatch('open-invite')"><i class="bi bi-person-plus"></i> Invite someone</button>@endif
</x-page-title>

<div class="mb-4 flex gap-2">
    @foreach ([null => 'Everyone', 'freelancers' => 'Freelancers', 'staff' => 'Company team'] as $key => $label)
        <a href="{{ route('team.index', $key ? ['type' => $key] : []) }}" class="chip {{ ($type ?: null) === $key ? 'chip-active' : '' }}">{{ $label }}</a>
    @endforeach
</div>

@if ($invitations->isNotEmpty() && $canManage)
    <div class="card mb-6">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Pending invitations</h2></div>
        <ul class="divide-y divide-slate-100">
            @foreach ($invitations as $inv)
                <li class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm" x-data="{ copied: false }">
                    <div class="min-w-0 flex-1"><span class="font-medium text-slate-900">{{ $inv->email }}</span>
                        <span class="text-slate-500"> · {{ $inv->member_type === 'freelancer' ? 'Freelancer' : str_replace('_', ' ', ucfirst($inv->role)) }} · expires {{ $inv->expires_at->diffForHumans() }}</span></div>
                    <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText(@js(route('invite.show', $inv->token))); copied = true; setTimeout(() => copied = false, 2000)"><i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i> <span x-text="copied ? 'Copied' : 'Copy link'"></span></button>
                    <form method="POST" action="{{ route('team.revoke', $inv->id) }}">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600">Cancel</button></form>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card divide-y divide-slate-100">
    @forelse ($members as $m)
        @php $profile = $m->user->freelancerProfile; @endphp
        <div class="flex flex-wrap items-center gap-3 px-5 py-4" x-data="{ edit: false }">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-100 font-bold text-brand-700">{{ strtoupper(substr($m->user->name, 0, 1)) }}</span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-slate-900">{{ $m->user->name }} @if ($m->user_id === auth()->id())<span class="text-xs font-normal text-slate-400">(you)</span>@endif</div>
                <div class="truncate text-sm text-slate-500">{{ $m->user->email }}@if ($profile?->headline) · {{ $profile->headline }}@endif</div>
            </div>
            <x-pill :tone="$m->isFreelancer() ? 'indigo' : 'blue'">{{ $m->role->label() }}</x-pill>
            @if ($m->status !== 'active')<x-pill tone="amber">{{ str_replace('_', ' ', ucfirst($m->status)) }}</x-pill>@endif
            @if ($m->isFreelancer() && $m->default_rate_minor && Gate::allows('see-money'))
                <span class="text-sm text-slate-500">{{ money($m->default_rate_minor, $m->default_rate_currency ?? $org->base_currency) }}/hr</span>
            @endif
            @if ($canManage)
                <button type="button" @click="edit = !edit" class="btn-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</button>
                <form method="POST" action="{{ route('team.update', $m->id) }}" x-show="edit" x-cloak class="mt-2 grid w-full gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-4">
                    @csrf @method('PATCH')
                    @unless ($m->isFreelancer())
                        <div><label class="label">Role</label>
                            <select name="role" class="input">@foreach ($roles as $r)<option value="{{ $r->value }}" @selected($m->role === $r)>{{ $r->label() }}</option>@endforeach</select></div>
                    @endunless
                    <div><label class="label">Status</label>
                        <select name="status" class="input">@foreach (['active' => 'Active', 'on_hold' => 'On hold', 'inactive' => 'Inactive'] as $k => $l)<option value="{{ $k }}" @selected($m->status === $k)>{{ $l }}</option>@endforeach</select></div>
                    @if ($m->isFreelancer())
                        <div><label class="label">Default hourly rate</label><input name="default_rate" type="number" step="0.01" min="0" value="{{ \App\Support\Money::toInput($m->default_rate_minor) }}" class="input"></div>
                        <div><label class="label">Currency</label>
                            <select name="default_rate_currency" class="input">@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected(($m->default_rate_currency ?? $org->base_currency) === $c)>{{ $c }}</option>@endforeach</select></div>
                    @endif
                    <div class="flex items-end"><button class="btn-primary w-full">Save</button></div>
                </form>
            @endif
        </div>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-500">No one here yet.</p>
    @endforelse
</div>

@if ($canManage)
    <div x-data="{ open: false, kind: 'freelancer' }" @open-invite.window="open = true" x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4">
        <form method="POST" action="{{ route('team.invite') }}" @click.outside="open = false" class="card w-full max-w-md space-y-4 p-6">
            @csrf
            <div class="flex items-start justify-between"><h2 class="text-lg font-bold text-slate-900">Invite someone</h2>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
            <div class="grid grid-cols-2 gap-2">
                <label :class="kind === 'freelancer' ? 'border-brand-600 bg-brand-50' : 'border-slate-200'" class="cursor-pointer rounded-xl border p-3 text-center text-sm font-medium"><input type="radio" name="kind" value="freelancer" x-model="kind" class="sr-only">Freelancer</label>
                <label :class="kind === 'employee' ? 'border-brand-600 bg-brand-50' : 'border-slate-200'" class="cursor-pointer rounded-xl border p-3 text-center text-sm font-medium"><input type="radio" name="kind" value="employee" x-model="kind" class="sr-only">Company team member</label>
            </div>
            <div><label class="label" for="inv-email">Email</label><input id="inv-email" name="email" type="email" required class="input" placeholder="name@example.com"></div>
            <div x-show="kind === 'employee'"><label class="label" for="inv-role">Role</label>
                <select id="inv-role" name="role" class="input">@foreach ($roles as $r)@continue($r->value === 'owner' && $role?->value !== 'owner')<option value="{{ $r->value }}" @selected($r->value === 'team_member')>{{ $r->label() }}</option>@endforeach</select></div>
            <p class="text-xs text-slate-500">They get a link to join. If email is not set up on this server you can copy the link and send it yourself.</p>
            <button class="btn-primary w-full">Create invitation</button>
        </form>
    </div>
@endif
@endsection
