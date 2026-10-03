@extends('layouts.app')
@section('title', 'Invitations')
@section('content')
@php $canManage = Gate::allows('manage-team'); @endphp
<x-page-title title="Team" sub="People you have invited to join {{ $org->name }}.">
    @if ($canManage)<button type="button" class="btn-primary" @click="$dispatch('open-invite')"><i class="bi bi-person-plus"></i> Invite someone</button>@endif
</x-page-title>
@include('team._tabs', ['active' => 'invitations'])

<div class="card mb-6">
    <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Waiting to be accepted</h2></div>
    @forelse ($pending as $inv)
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0" x-data="{ copied: false }">
            <i class="bi bi-envelope text-lg text-slate-400"></i>
            <div class="min-w-0 flex-1"><div class="truncate font-medium text-slate-900">{{ $inv->email }}</div>
                <div class="text-xs text-slate-500">{{ $inv->member_type === 'freelancer' ? 'Freelancer' : ucfirst(str_replace('_', ' ', $inv->role)) }} · expires {{ $inv->expires_at->diffForHumans() }}</div></div>
            @if ($canManage)
                <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText(@js(route('invite.show', $inv->token))); copied = true; setTimeout(() => copied = false, 2000)"><i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i> <span x-text="copied ? 'Copied' : 'Copy link'"></span></button>
                <form method="POST" action="{{ route('team.revoke', $inv->id) }}">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600">Cancel</button></form>
            @endif
        </div>
    @empty<p class="px-5 py-8 text-center text-sm text-slate-500">No invitations waiting.</p>@endforelse
</div>
@if ($history->isNotEmpty())
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">History</h2></div>
        @foreach ($history as $inv)
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0"><span class="truncate text-slate-700">{{ $inv->email }}</span>
                @if ($inv->accepted_at)<x-pill tone="green">Joined {{ $inv->accepted_at->format('d M') }}</x-pill>@else<x-pill>Expired</x-pill>@endif</div>
        @endforeach
    </div>
@endif
@if ($canManage)@include('team._invite-modal')@endif
@endsection
