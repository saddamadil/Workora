@php
    $labels = [
        'project.created' => 'Project created', 'task.created' => 'Task created', 'message.sent' => 'Message sent', 'file.uploaded' => 'File uploaded', 'file.shared' => 'File shared with client',
        'file.unshared' => 'File unshared', 'milestone.created' => 'Milestone added', 'milestone.completed' => 'Milestone completed', 'invoice.sent' => 'Invoice sent', 'invoice.viewed' => 'Invoice viewed by client',
        'invoice.approved' => 'Invoice approved', 'payment.recorded' => 'Payment recorded', 'deliverable.submitted' => 'Deliverable sent for review', 'deliverable.approved' => 'Deliverable approved',
        'deliverable.changes_requested' => 'Changes requested', 'request.created' => 'Work request received', 'request.accepted' => 'Work request accepted', 'request.declined' => 'Work request declined',
        'request.discussing' => 'Work request under discussion', 'request.in_progress' => 'Work request in progress', 'request.completed' => 'Work request completed',
    ];
@endphp
<div class="card">
    <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Activity</h2></div>
    @forelse ($activity as $a)
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
            <span class="text-slate-800">{{ $labels[$a->action] ?? ucfirst(str_replace(['.', '_'], ' ', $a->action)) }}@if ($a->user) <span class="text-slate-500">· {{ $a->user->name }}</span>@endif</span>
            <time class="shrink-0 text-xs text-slate-500" datetime="{{ $a->created_at->toIso8601String() }}">{{ $a->created_at->format('d M, H:i') }}</time>
        </div>
    @empty<p class="px-5 py-8 text-center text-sm text-slate-500">Nothing has happened on this project yet.</p>@endforelse
</div>
