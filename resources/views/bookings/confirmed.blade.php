@extends('layouts.guest')
@section('title', 'Your booking')
@section('content')
<div class="card space-y-3 p-6 text-sm">
    @if ($booking->status === 'cancelled')<h1 class="text-xl font-bold text-slate-900">Booking cancelled</h1><p class="text-slate-600">This meeting is cancelled.</p><a href="{{ route('book.show', $page->slug) }}" class="btn-primary">Pick another time</a>
    @else
        <h1 class="text-xl font-bold text-slate-900"><i class="bi bi-check-circle-fill text-green-600"></i> You are booked</h1>
        <p class="text-slate-700">{{ $page->title }} with {{ $page->host->name }}</p>
        <p class="font-semibold text-slate-900">{{ $booking->starts_at->copy()->setTimezone($page->timezone)->format('l d F Y, H:i') }} <span class="font-normal text-slate-500">({{ $page->timezone }}, {{ $page->duration_minutes }} minutes)</span></p>
        @if ($page->location)<p class="text-slate-600">Where: {{ $page->location }}</p>@endif
        @if ($booking->starts_at->isFuture())<form method="POST" action="{{ route('book.cancel', $booking->token) }}" onsubmit="return confirm('Cancel this booking?')">@csrf<button class="btn-secondary btn-sm text-red-600">Cancel this booking</button></form>@endif
    @endif
</div>
@endsection
