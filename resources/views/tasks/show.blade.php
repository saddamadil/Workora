@extends('layouts.app')
@section('title', $task->title)
@section('content')
@php
    $priorityTone = ['low' => 'slate', 'medium' => 'blue', 'high' => 'orange', 'urgent' => 'red'];
    $latest = $task->submissions->first();
    $isFreelancer = $role->isFreelancer();
@endphp
<div class="mb-2 text-sm"><a href="{{ route('projects.show', $task->project) }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> {{ $task->project->name }}</a></div>
<x-page-title :title="$task->title">
    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ \App\Models\Task::STATUS_STYLES[$task->status] ?? '' }}">{{ $task->statusLabel() }}</span>
    <x-pill :tone="$priorityTone[$task->priority] ?? 'slate'">{{ ucfirst($task->priority) }}</x-pill>
</x-page-title>

<div class="grid gap-6 lg:grid-cols-3">
<div class="space-y-6 lg:col-span-2">

    @if ($task->description)<div class="card whitespace-pre-line p-5 text-sm text-slate-700">{{ $task->description }}</div>@endif

    {{-- Changes the reviewer asked for --}}
    @if ($task->revisions->isNotEmpty())
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900"><i class="bi bi-arrow-repeat text-orange-500"></i> Requested changes</h2></div>
            <ul class="divide-y divide-slate-100">
                @foreach ($task->revisions as $r)
                    <li class="flex items-start gap-3 px-5 py-3 text-sm">
                        <i class="bi {{ $r->status === 'resolved' ? 'bi-check-circle-fill text-emerald-500' : 'bi-circle text-orange-500' }} mt-0.5"></i>
                        <span class="{{ $r->status === 'resolved' ? 'text-slate-400 line-through' : 'text-slate-800' }}">{{ $r->issue }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Checklist --}}
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Checklist</h2></div>
        <ul class="divide-y divide-slate-100">
            @foreach ($task->checklistItems as $item)
                <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                    <form method="POST" action="{{ route('tasks.checklist.toggle', [$task, $item]) }}">@csrf @method('PATCH')
                        <button class="text-lg {{ $item->is_done ? 'text-emerald-500' : 'text-slate-300 hover:text-slate-500' }}" aria-label="Toggle {{ $item->title }}"><i class="bi {{ $item->is_done ? 'bi-check-square-fill' : 'bi-square' }}"></i></button></form>
                    <span class="flex-1 {{ $item->is_done ? 'text-slate-400 line-through' : 'text-slate-800' }}">{{ $item->title }}</span>
                    @if ($canEdit || $canWork)<form method="POST" action="{{ route('tasks.checklist.delete', [$task, $item]) }}">@csrf @method('DELETE')<button class="text-slate-300 hover:text-red-600" aria-label="Delete item"><i class="bi bi-x-lg"></i></button></form>@endif
                </li>
            @endforeach
        </ul>
        @if ($canEdit || $canWork)
            <form method="POST" action="{{ route('tasks.checklist.add', $task) }}" class="flex gap-2 border-t border-slate-100 p-3">@csrf
                <input name="title" required maxlength="200" class="input" placeholder="Add a step…" aria-label="New checklist item"><button class="btn-secondary">Add</button></form>
        @endif
    </div>

    {{-- Submissions --}}
    @if ($task->submissions->isNotEmpty())
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Submitted work</h2></div>
            <ul class="divide-y divide-slate-100">
                @foreach ($task->submissions as $sub)
                    <li class="p-5 text-sm">
                        <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-slate-900">Attempt {{ $sub->attempt }}</span>
                            <x-pill :tone="['approved' => 'green', 'rejected' => 'orange', 'pending' => 'amber'][$sub->status] ?? 'slate'">{{ $sub->status === 'rejected' ? 'Changes requested' : ucfirst($sub->status) }}</x-pill>
                            <span class="text-xs text-slate-500">by {{ $sub->submittedBy->name }} · {{ $sub->submitted_at->diffForHumans() }}</span></div>
                        <p class="mt-2 whitespace-pre-line text-slate-700">{{ $sub->note }}</p>
                        @foreach ($submissionFiles[$sub->id] ?? [] as $f)
                            <a href="{{ route('files.download', $f) }}" class="mr-2 mt-2 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-50"><i class="bi bi-paperclip"></i> {{ $f->original_name }} <span class="text-slate-400">{{ $f->humanSize() }}</span></a>
                        @endforeach
                        @if ($sub->review_note)<p class="mt-2 rounded-lg bg-slate-50 p-2.5 text-slate-600"><strong>{{ $sub->reviewedBy?->name }}:</strong> {{ $sub->review_note }}</p>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Files --}}
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Files</h2></div>
        @if ($task->files->isNotEmpty())
            <ul class="divide-y divide-slate-100">@foreach ($task->files as $f) @php [$icon, $tint] = $f->icon(); @endphp
                <li class="flex items-center gap-3 px-5 py-2.5 text-sm"><span class="grid size-8 place-items-center rounded-lg {{ $tint }}"><i class="bi {{ $icon }}"></i></span>
                    <a href="{{ route('files.show', $f) }}" target="_blank" class="min-w-0 flex-1 truncate font-medium text-slate-800 hover:text-brand-600">{{ $f->original_name }}</a><span class="text-xs text-slate-400">{{ $f->humanSize() }}</span>
                    <a href="{{ route('files.download', $f) }}" class="text-slate-400 hover:text-slate-700" aria-label="Download"><i class="bi bi-download"></i></a></li>
            @endforeach</ul>
        @endif
        @if ($canEdit || $canWork)
            <form method="POST" action="{{ route('tasks.attach', $task) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2 border-t border-slate-100 p-3 first:border-0">@csrf
                <input type="file" name="files[]" multiple required class="min-w-0 flex-1 text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium" aria-label="Files to attach"><button class="btn-secondary">Attach</button></form>
        @elseif ($task->files->isEmpty())<p class="px-5 py-6 text-center text-sm text-slate-500">No files attached.</p>@endif
    </div>

    {{-- Comments --}}
    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Discussion</h2></div>
        <ul class="divide-y divide-slate-100">
            @forelse ($task->comments as $c)
                <li class="flex gap-3 px-5 py-3 text-sm"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ strtoupper(substr($c->user->name, 0, 1)) }}</span>
                    <div><div><span class="font-semibold text-slate-900">{{ $c->user->name }}</span> <span class="text-xs text-slate-400">{{ $c->created_at->diffForHumans() }}</span></div><p class="whitespace-pre-line text-slate-700">{{ $c->body }}</p></div></li>
            @empty<li class="px-5 py-6 text-center text-sm text-slate-500">No comments yet.</li>@endforelse
        </ul>
        <form method="POST" action="{{ route('tasks.comment', $task) }}" class="flex gap-2 border-t border-slate-100 p-3">@csrf
            <input name="body" required maxlength="3000" class="input" placeholder="Write a comment…" aria-label="Comment"><button class="btn-primary">Post</button></form>
    </div>
</div>

{{-- Side column --}}
<div class="space-y-6">
    {{-- Working --}}
    @if ($canWork)
        <div class="card space-y-3 p-5">
            <h2 class="font-semibold text-slate-900">Your work</h2>
            @if (in_array($task->status, ['assigned', 'revision_required']))
                <form method="POST" action="{{ route('tasks.start', $task) }}">@csrf<button class="btn-primary w-full"><i class="bi bi-play-fill"></i> Start working</button></form>
            @endif
            @if (Route::has('time.start') && Gate::allows('track-time') && ! $task->isClosed())
                <form method="POST" action="{{ route('time.start') }}">@csrf<input type="hidden" name="task_id" value="{{ $task->id }}"><button class="btn-secondary w-full"><i class="bi bi-stopwatch"></i> Start timer</button></form>
            @endif
            @if ($org->mode === 'solo' && ! $task->isClosed())
                <form method="POST" action="{{ route('tasks.complete', $task) }}">@csrf<button class="btn-primary w-full"><i class="bi bi-check2-circle"></i> Mark as complete</button></form>
            @elseif ($task->canBeSubmitted())
                <form method="POST" action="{{ route('tasks.submit', $task) }}" enctype="multipart/form-data" class="space-y-3 border-t border-slate-100 pt-3">@csrf
                    <label class="label" for="note">Hand in your work</label>
                    <textarea id="note" name="note" rows="3" required class="input" placeholder="What did you do? Anything the reviewer should check?"></textarea>
                    <input type="file" name="files[]" multiple class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium" aria-label="Deliverable files">
                    <button class="btn-primary w-full"><i class="bi bi-send"></i> Submit for review</button>
                </form>
            @elseif (in_array($task->status, ['submitted', 'under_review']))
                <p class="text-sm text-slate-500">Submitted. You will see the result here once it is reviewed.</p>
            @endif
        </div>
    @endif

    {{-- Reviewing --}}
    @if ($canReview && in_array($task->status, ['submitted', 'under_review']))
        <div class="card space-y-3 border-amber-200 p-5" x-data="{ mode: 'approve' }">
            <h2 class="font-semibold text-slate-900"><i class="bi bi-eye text-amber-500"></i> Review this work</h2>
            <form method="POST" action="{{ route('tasks.review', $task) }}" class="space-y-3">@csrf
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <label :class="mode === 'approve' ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200'" class="cursor-pointer rounded-xl border p-2.5 text-center font-medium"><input type="radio" name="decision" value="approve" x-model="mode" class="sr-only">Approve</label>
                    <label :class="mode === 'revise' ? 'border-orange-500 bg-orange-50' : 'border-slate-200'" class="cursor-pointer rounded-xl border p-2.5 text-center font-medium"><input type="radio" name="decision" value="revise" x-model="mode" class="sr-only">Request changes</label>
                </div>
                <div x-show="mode === 'revise'" x-cloak><label class="label" for="issues">What needs to change? One item per line.</label>
                    <textarea id="issues" name="issues" rows="4" class="input" :required="mode === 'revise'"></textarea></div>
                <div><label class="label" for="rnote">Note <span class="font-normal text-slate-400">(optional)</span></label><input id="rnote" name="note" class="input"></div>
                <button class="btn-primary w-full" x-text="mode === 'approve' ? 'Approve work' : 'Send back'"></button>
            </form>
        </div>
    @endif

    {{-- Details --}}
    <div class="card divide-y divide-slate-100 text-sm">
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Assigned to</span><span class="text-right font-medium text-slate-900">{{ $task->assignees->pluck('name')->join(', ') ?: 'Nobody yet' }}</span></div>
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Due</span><span class="font-medium {{ $task->isOverdue() ? 'text-red-600' : 'text-slate-900' }}">{{ $task->due_at?->format('d M Y') ?? 'No date' }}</span></div>
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Estimate</span><span class="font-medium text-slate-900">{{ $task->estimated_hours ? $task->estimated_hours.' h' : '—' }}</span></div>
        <div class="flex justify-between px-5 py-3"><span class="text-slate-500">Logged</span><span class="font-medium text-slate-900">{{ number_format((float) $task->actual_hours, 2) }} h</span></div>
        @can('see-money')@if ($task->budget_minor)<div class="flex justify-between px-5 py-3"><span class="text-slate-500">Budget</span><span class="font-medium text-slate-900">{{ money($task->budget_minor, $task->currency ?? $org->base_currency) }}</span></div>@endif @endcan
        @if ($task->approved_at)<div class="flex justify-between px-5 py-3"><span class="text-slate-500">Approved</span><span class="font-medium text-emerald-700">{{ $task->approved_at->format('d M Y') }}</span></div>@endif
    </div>

    @if ($entries->isNotEmpty())
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Recent time</h2></div>
            <ul class="divide-y divide-slate-100 text-sm">@foreach ($entries as $e)<li class="flex justify-between px-5 py-2.5"><span class="text-slate-600">{{ $e->entry_date->format('d M') }} · {{ $e->user->name }}</span><span class="font-medium text-slate-900">{{ hours($e->minutes) }}</span></li>@endforeach</ul>
        </div>
    @endif

    {{-- Edit --}}
    @if ($canEdit)
        <details class="card p-5 text-sm">
            <summary class="cursor-pointer font-semibold text-slate-900">Edit task</summary>
            <form method="POST" action="{{ route('tasks.update', $task) }}" class="mt-4 space-y-3">@csrf @method('PATCH')
                <div><label class="label">Title</label><input name="title" value="{{ $task->title }}" required class="input"></div>
                <div><label class="label">Details</label><textarea name="description" rows="3" class="input">{{ $task->description }}</textarea></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Priority</label><select name="priority" class="input">@foreach (['low', 'medium', 'high', 'urgent'] as $p)<option value="{{ $p }}" @selected($task->priority === $p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
                    <div><label class="label">Due</label><input type="date" name="due_at" value="{{ $task->due_at?->format('Y-m-d') }}" class="input"></div>
                    <div><label class="label">Estimate (h)</label><input type="number" step="0.25" min="0" name="estimated_hours" value="{{ $task->estimated_hours }}" class="input"></div>
                    @can('see-money')<div><label class="label">Budget</label><input type="number" step="0.01" min="0" name="budget" value="{{ \App\Support\Money::toInput($task->budget_minor) }}" class="input"></div>@endcan
                </div>
                <fieldset><legend class="label">Assigned to</legend>
                    <div class="max-h-36 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-2">
                        @foreach ($candidates as $u)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="assignee_ids[]" value="{{ $u->id }}" @checked($task->assignees->contains('id', $u->id)) class="rounded border-slate-300"> {{ $u->name }}</label>@endforeach
                    </div></fieldset>
                <button class="btn-primary w-full">Save</button>
            </form>
            <div class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                @if ($task->status === 'cancelled')
                    <form method="POST" action="{{ route('tasks.reopen', $task) }}" class="flex-1">@csrf<button class="btn-secondary w-full">Reopen</button></form>
                @elseif ($task->status !== 'approved')
                    <form method="POST" action="{{ route('tasks.cancel', $task) }}" class="flex-1" onsubmit="return confirm('Cancel this task?')">@csrf<button class="btn-secondary w-full">Cancel task</button></form>
                @endif
                @can('delete', $task)<form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task for good?')">@csrf @method('DELETE')<button class="btn-secondary text-red-600" aria-label="Delete task"><i class="bi bi-trash"></i></button></form>@endcan
            </div>
        </details>
    @endif
</div>
</div>
@endsection
