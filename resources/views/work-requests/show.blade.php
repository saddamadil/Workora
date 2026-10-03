@extends('layouts.app')
@section('title', $wr->title)
@section('content')
@php $tone = ['submitted' => 'amber', 'negotiating' => 'amber', 'under_review' => 'amber', 'approved' => 'green', 'rejected' => 'red', 'withdrawn' => 'slate']; @endphp
<div class="mb-2 text-sm"><a href="{{ route('work-requests.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Proposals</a></div>
<x-page-title :title="$wr->title" :sub="'From '.$wr->requestedBy->name.' · '.$wr->created_at->format('d M Y')">
    <x-pill :tone="$tone[$wr->status] ?? 'slate'">{{ ucfirst(str_replace('_', ' ', $wr->status)) }}</x-pill>
</x-page-title>
<div class="grid gap-6 lg:grid-cols-3">
<div class="space-y-6 lg:col-span-2">
    <div class="card whitespace-pre-line p-5 text-sm text-slate-700">{{ $wr->description }}</div>
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Conversation</h2></div>
        <ul class="divide-y divide-slate-100 text-sm">
            @forelse ($wr->messages as $m)
                <li class="px-5 py-3"><span class="font-semibold text-slate-900">{{ $m->user->name }}</span> <span class="text-xs text-slate-400">{{ $m->created_at->diffForHumans() }}</span>
                    @if ($m->body)<p class="whitespace-pre-line text-slate-700">{{ $m->body }}</p>@endif
                    @if ($m->proposed_amount_minor || $m->proposed_hours || $m->proposed_deadline)<p class="mt-1 text-xs font-medium text-brand-700">Counter: @if ($m->proposed_amount_minor){{ money($m->proposed_amount_minor, $wr->currency) }} @endif @if ($m->proposed_hours)· {{ $m->proposed_hours }} h @endif @if ($m->proposed_deadline)· by {{ $m->proposed_deadline->format('d M') }}@endif</p>@endif</li>
            @empty<li class="px-5 py-6 text-center text-slate-500">No messages yet.</li>@endforelse
        </ul>
        @if ($wr->isOpen())
            <form method="POST" action="{{ route('work-requests.message', $wr) }}" class="space-y-2 border-t border-slate-100 p-4">@csrf
                <input name="body" class="input" placeholder="Write a message…" aria-label="Message">
                <div class="flex flex-wrap gap-2"><input name="amount" type="number" step="0.01" min="0.01" class="input w-40" placeholder="Counter price" aria-label="Counter price"><input name="hours" type="number" step="0.5" min="0" class="input w-28" placeholder="Hours" aria-label="Hours"><input name="deadline" type="date" class="input w-44" aria-label="Deadline"><button class="btn-primary ms-auto">Send</button></div></form>
        @endif
    </div>
</div>
<div class="space-y-6">
    <div class="card divide-y divide-slate-100 text-sm">
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Current price</span><span class="font-semibold text-slate-900">{{ money($currentAmount, $wr->currency) }}</span></div>
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Hours</span><span class="font-medium text-slate-900">{{ $wr->estimated_hours ?? '—' }}</span></div>
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Finish by</span><span class="font-medium text-slate-900">{{ $wr->proposed_deadline?->format('d M Y') ?? '—' }}</span></div>
    </div>
    @if ($wr->response_note)<p class="card p-4 text-sm text-slate-700"><strong>Response:</strong> {{ $wr->response_note }}</p>@endif
    @if ($wr->convertedTask)<a href="{{ route('tasks.show', $wr->convertedTask) }}" class="btn-primary w-full"><i class="bi bi-check2-square"></i> Open the task</a>@endif
    @if ($canDecide)
        <div class="card space-y-3 border-amber-200 p-5"><h2 class="font-semibold text-slate-900">Decide</h2>
            <form method="POST" action="{{ route('work-requests.approve', $wr) }}" class="space-y-2">@csrf
                <label class="label" for="proj">Put it on which project?</label>
                <select id="proj" name="project_id" required class="input"><option value="">Choose…</option>@foreach ($projects as $p)<option value="{{ $p->id }}" @selected($wr->project_id === $p->id)>{{ $p->name }}</option>@endforeach</select>
                <input name="note" class="input" placeholder="Note (optional)" aria-label="Note"><button class="btn-primary w-full">Approve at {{ money($currentAmount, $wr->currency) }}</button></form>
            <form method="POST" action="{{ route('work-requests.reject', $wr) }}" class="space-y-2 border-t border-slate-100 pt-3">@csrf
                <input name="note" required class="input" placeholder="Why not?" aria-label="Reason"><button class="btn-secondary w-full">Decline</button></form></div>
    @endif
    @if ($isOwner && $wr->isOpen())<form method="POST" action="{{ route('work-requests.withdraw', $wr) }}" onsubmit="return confirm('Withdraw this proposal?')">@csrf<button class="btn-secondary w-full">Withdraw proposal</button></form>@endif
</div>
</div>
@endsection
