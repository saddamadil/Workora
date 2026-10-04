@extends('layouts.app')
@section('title', $quote->number)
@section('content')
@php $tone = ['draft' => 'slate', 'sent' => 'amber', 'accepted' => 'green', 'declined' => 'red']; @endphp
<div class="mb-2 text-sm"><a href="{{ route('quotes.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Quotes</a></div>
<x-page-title :title="$quote->number" :sub="$quote->client?->name.($quote->valid_until ? ' · valid until '.$quote->valid_until->format('d M Y') : '')">
    <x-pill :tone="$tone[$quote->status] ?? 'slate'">{{ ucfirst($quote->status) }}</x-pill>
    @if ($quote->status === 'draft')<a href="{{ route('quotes.edit', $quote) }}" class="btn-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>@endif
    @if (in_array($quote->status, ['draft', 'sent'], true))<form method="POST" action="{{ route('quotes.send', $quote) }}">@csrf<button class="btn-primary btn-sm"><i class="bi bi-send"></i> {{ $quote->status === 'sent' ? 'Send again' : 'Send to client' }}</button></form>@endif
    @if (in_array($quote->status, ['draft', 'declined'], true))<form method="POST" action="{{ route('quotes.destroy', $quote) }}" onsubmit="return confirm('Delete this quote?')">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600"><i class="bi bi-trash"></i> Delete</button></form>@endif
</x-page-title>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">@include('quotes._document')</div>
    <div class="card h-fit space-y-2 p-5 text-sm">
        <h2 class="font-semibold text-slate-900">Status</h2>
        @if ($quote->status === 'accepted')
            <p class="text-slate-700">Accepted by <strong>{{ $quote->signed_name }}</strong> on {{ $quote->responded_at?->format('d M Y, H:i') }}.</p>
            @if ($quote->project)<a href="{{ route('projects.show', $quote->project) }}" class="btn-primary btn-sm"><i class="bi bi-kanban"></i> Open the project</a>@endif
        @elseif ($quote->status === 'declined')<p class="text-slate-700">Declined on {{ $quote->responded_at?->format('d M Y') }}.@if ($quote->decline_reason) Reason: {{ $quote->decline_reason }}@endif</p>
        @elseif ($quote->status === 'sent')<p class="text-slate-700">Sent {{ $quote->sent_at?->diffForHumans() }}. Waiting for the client.@if ($quote->isExpired()) <strong>It has expired.</strong>@endif</p>
        @else<p class="text-slate-700">Draft. The client cannot see it yet.</p>@endif
    </div>
</div>
@endsection
