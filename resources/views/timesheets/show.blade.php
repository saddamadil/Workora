@extends('layouts.app')
@section('title', 'Timesheet')
@section('content')
@php $tone = ['draft' => 'slate', 'submitted' => 'amber', 'approved' => 'green', 'rejected' => 'orange']; @endphp
<div class="mb-2 text-sm"><a href="{{ route('timesheets.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Timesheets</a></div>
<x-page-title :title="$timesheet->freelancer->name" :sub="$timesheet->period_start->format('d M').' – '.$timesheet->period_end->format('d M Y')">
    <x-pill :tone="$tone[$timesheet->status] ?? 'slate'">{{ ucfirst($timesheet->status) }}</x-pill>
</x-page-title>
<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <x-stat label="Total hours" :value="hours((int) $timesheet->entries->sum('minutes'))" icon="bi-stopwatch" :hint="hours($timesheet->total_minutes).' billable'" />
    @can('see-money')<x-stat label="Billable value" :value="money($timesheet->total_amount_minor, $timesheet->currency)" icon="bi-cash-coin" tone="green" />@endcan
    <x-stat label="Submitted" :value="$timesheet->submitted_at?->diffForHumans() ?? '—'" icon="bi-send" tone="slate" />
</div>
<div class="card mb-6 overflow-x-auto">
    <table class="w-full text-start text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Project / task</th><th class="px-5 py-3">Note</th><th class="px-5 py-3 text-end">Time</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($timesheet->entries as $e)
                <tr><td class="whitespace-nowrap px-5 py-2.5 text-slate-600">{{ $e->entry_date->format('D d M') }}</td>
                    <td class="px-5 py-2.5 font-medium text-slate-900">{{ $e->project->name }}@if ($e->task) <span class="font-normal text-slate-500">· {{ $e->task->title }}</span>@endif @unless ($e->is_billable)<x-pill class="ms-1">Non-billable</x-pill>@endunless</td>
                    <td class="px-5 py-2.5 text-slate-500">{{ $e->description }}</td>
                    <td class="whitespace-nowrap px-5 py-2.5 text-end font-medium text-slate-900">{{ hours($e->minutes) }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>
@if ($timesheet->review_note)<p class="mb-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-700"><strong>{{ $timesheet->reviewedBy?->name }}:</strong> {{ $timesheet->review_note }}</p>@endif
@if ($timesheet->status === 'submitted')
    <div class="grid gap-4 md:grid-cols-2">
        <form method="POST" action="{{ route('timesheets.approve', $timesheet) }}" class="card space-y-3 p-5">@csrf
            <h2 class="font-semibold text-slate-900">Approve</h2>
            <input name="note" class="input" placeholder="Optional note" aria-label="Approval note">
            <button class="btn-primary w-full"><i class="bi bi-check2-circle"></i> Approve timesheet</button></form>
        <form method="POST" action="{{ route('timesheets.reject', $timesheet) }}" class="card space-y-3 p-5">@csrf
            <h2 class="font-semibold text-slate-900">Send back</h2>
            <input name="note" required class="input" placeholder="What needs to change?" aria-label="Reason">
            <button class="btn-secondary w-full">Send back for changes</button></form>
    </div>
@endif
@endsection
