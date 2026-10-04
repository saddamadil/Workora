@extends('layouts.app')
@section('title', $quote->number)
@section('content')
<div class="mb-2 text-sm"><a href="{{ route('portal.quotes') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Quotes</a></div>
<x-page-title :title="$quote->title" :sub="$quote->number.($quote->valid_until ? ' · valid until '.$quote->valid_until->format('d M Y') : '')" />
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">@include('quotes._document')</div>
    <div class="card h-fit space-y-3 p-5 text-sm">
        @if ($quote->status === 'accepted')
            <p class="text-slate-700">Accepted by <strong>{{ $quote->signed_name }}</strong> on {{ $quote->responded_at?->format('d M Y') }}.</p>
            @if ($quote->project)<a href="{{ route('portal.project', $quote->project->slug) }}" class="btn-primary btn-sm"><i class="bi bi-kanban"></i> Open the project</a>@endif
        @elseif ($quote->status === 'declined')<p class="text-slate-700">You declined this quote.</p>
        @elseif ($quote->isExpired())<p class="text-slate-700">This quote has expired. Ask your freelancer for a new one.</p>
        @elseif (app(\App\Support\Tenancy::class)->isClientOwner())
            <form method="POST" action="{{ route('portal.quotes.accept', $quote) }}" class="space-y-3">@csrf
                <h2 class="font-semibold text-slate-900">Accept this quote</h2>
                <div><label class="label" for="sn">Type your full name</label><input id="sn" name="signed_name" required maxlength="120" class="input">@error('signed_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <label class="flex items-start gap-2"><input type="checkbox" name="agree" value="1" required class="mt-1 rounded border-slate-300"><span>I accept this quote and its terms for {{ money($quote->totalMinor(), $quote->currency) }}.</span></label>
                <button class="btn-primary w-full">Accept and start</button></form>
            <form method="POST" action="{{ route('portal.quotes.decline', $quote) }}" class="space-y-2 border-t border-slate-100 pt-3">@csrf
                <input name="reason" maxlength="250" placeholder="Reason (optional)" class="input" aria-label="Reason"><button class="btn-secondary w-full">Decline</button></form>
        @else<p class="text-slate-700">Only the account owner can accept this quote.</p>@endif
    </div>
</div>
@endsection
