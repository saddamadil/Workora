@extends('layouts.app')
@section('title', 'Recurring invoices')
@section('content')
<x-page-title title="Recurring invoices" sub="Retainers and monthly services. An invoice is made on each date; time and milestone lines are not repeated. Open any invoice and choose Make recurring to add one." />
<div class="card">
    @forelse ($schedules as $r)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 text-sm last:border-0">
            <div>
                <p class="font-semibold text-slate-900">{{ $r->title }} <x-pill :tone="['active' => 'green', 'paused' => 'amber', 'ended' => 'slate'][$r->status]">{{ ucfirst($r->status) }}</x-pill></p>
                <p class="text-slate-500">{{ $r->client?->name }} · {{ $frequencies[$r->frequency] }} · {{ $totals[$r->id] }} · {{ $r->auto_send ? 'sent automatically' : 'kept as draft' }}</p>
                <p class="text-slate-500">@if ($r->status !== 'ended')Next: {{ $r->next_run_on->format('d M Y') }} · @endif{{ $r->runs_count }} made @if ($r->ends_on)· ends {{ $r->ends_on->format('d M Y') }}@endif</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($r->status !== 'ended')
                    <form method="POST" action="{{ route('recurring.run', $r) }}">@csrf<button class="btn-secondary btn-sm"><i class="bi bi-play-fill"></i> Run now</button></form>
                    <form method="POST" action="{{ route('recurring.toggle', $r) }}">@csrf<button class="btn-secondary btn-sm"><i class="bi bi-{{ $r->status === 'active' ? 'pause-fill' : 'play' }}"></i> {{ $r->status === 'active' ? 'Pause' : 'Resume' }}</button></form>
                @endif
                <form method="POST" action="{{ route('recurring.destroy', $r) }}" onsubmit="return confirm('Delete this schedule?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Delete</button></form>
            </div>
        </div>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-500">No schedules yet.</p>
    @endforelse
</div>
@endsection
