@extends('layouts.app')
@section('title', 'Quotes')
@section('content')
@php $tone = ['sent' => 'amber', 'accepted' => 'green', 'declined' => 'red']; @endphp
<x-page-title title="Quotes" sub="Offers from your freelancer. Accepting one sets up the project." />
<div class="card">
    @forelse ($quotes as $q)
        <a href="{{ route('portal.quotes.show', $q) }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 text-sm last:border-0 hover:bg-slate-50">
            <span><strong class="text-slate-900">{{ $q->title }}</strong><span class="block text-slate-500">{{ $q->number }}@if ($q->valid_until) · valid until {{ $q->valid_until->format('d M Y') }}@endif</span></span>
            <span class="flex items-center gap-3"><strong>{{ money($q->totalMinor(), $q->currency) }}</strong><x-pill :tone="$tone[$q->status] ?? 'slate'">{{ $q->status === 'sent' && $q->isExpired() ? 'Expired' : ucfirst($q->status) }}</x-pill></span></a>
    @empty<p class="px-5 py-12 text-center text-sm text-slate-500">No quotes yet.</p>@endforelse
</div>
@endsection
