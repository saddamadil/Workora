@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<x-page-title :title="'Hello, '.explode(' ', auth()->user()->name)[0]" :sub="$org->name.' · '.($role?->label())">
    @can('track-time')<a href="{{ route('time.index') }}" class="btn-secondary"><i class="bi bi-stopwatch"></i> Track time</a>@endcan
    @if ($mode === 'staff') @can('create', \App\Models\Project::class)<a href="{{ route('projects.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> New project</a>@endcan @endif
</x-page-title>

@if ($mode === 'freelancer')
    @if ($contractsToAccept)
        <a href="{{ route('contracts.index') }}" class="mb-5 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><i class="bi bi-file-earmark-ruled text-lg"></i> You have {{ $contractsToAccept }} contract{{ $contractsToAccept > 1 ? 's' : '' }} waiting for your answer. <span class="ms-auto font-semibold">Review <i class="bi bi-arrow-right"></i></span></a>
    @endif
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Hours this week" :value="hours($hoursThisWeek)" icon="bi-stopwatch" :hint="hours($hoursThisMonth).' this month'" :href="route('time.index')" />
        <x-stat label="Approved, not yet invoiced" :value="money($unbilledMinor, $currency)" icon="bi-hourglass-split" tone="amber" :href="route('invoices.index')" />
        <x-stat label="Awaiting payment" :value="money($awaitingPayment, $currency)" icon="bi-receipt" tone="slate" :hint="$inApproval.' invoice(s) in approval'" :href="route('invoices.index')" />
        <x-stat label="Paid this month" :value="money($paidThisMonth, $currency)" icon="bi-cash-coin" tone="green" :href="route('payments.index')" />
    </div>
    <div class="card">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="font-semibold text-slate-900">Your open tasks</h2>
            <span class="text-xs text-slate-500">{{ $needChanges }} need changes · {{ $inReview }} in review</span>
        </div>
        @include('tasks._list', ['tasks' => $openTasks, 'empty' => 'Nothing assigned to you right now.'])
    </div>
@else
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Active projects" :value="$activeProjects" icon="bi-kanban" :href="route('projects.index', ['status' => 'active'])" />
        <x-stat label="Waiting for your review" :value="$toReview->count()" icon="bi-eye" tone="amber" :href="route('tasks.index', ['scope' => 'review'])" />
        <x-stat label="Overdue tasks" :value="$overdue" icon="bi-exclamation-triangle" :tone="$overdue ? 'red' : 'slate'" :href="route('tasks.index')" />
        <x-stat label="Hours this month" :value="hours($hoursThisMonth)" icon="bi-stopwatch" tone="green" />
        @if (! is_null($timesheetsPending))<x-stat label="Timesheets to approve" :value="$timesheetsPending" icon="bi-calendar-check" tone="amber" :href="route('timesheets.index')" />@endif
        @if (! is_null($invoicesPending))
            <x-stat label="Invoices to approve" :value="$invoicesPending" icon="bi-receipt" tone="amber" :href="route('invoices.index')" />
            <x-stat label="Approved, still to pay" :value="money($payable, $currency)" icon="bi-wallet2" tone="red" :href="route('payments.index')" />
            <x-stat label="Paid this month" :value="money($paidThisMonth, $currency)" icon="bi-cash-coin" tone="green" :href="route('payments.index')" />
        @endif
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Ready for review</h2></div>
            @include('tasks._list', ['tasks' => $toReview, 'empty' => 'No submitted work is waiting.'])
        </div>
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Due in the next 7 days</h2></div>
            @include('tasks._list', ['tasks' => $dueSoon, 'empty' => 'Nothing is due this week.'])
        </div>
    </div>
@endif
@endsection
