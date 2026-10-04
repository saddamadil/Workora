@php $tone = ['in_review' => 'amber', 'approved' => 'green', 'changes_requested' => 'orange']; @endphp
<div class="card">
    <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Deliverables for review</h2><p class="text-xs text-slate-500">Send finished work to your client. They approve it or ask for changes.</p></div>
    @forelse ($deliverables as $d)
        <div class="border-b border-slate-100 px-5 py-4 text-sm last:border-0">
            <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-semibold text-slate-900">{{ $d->title }}</span><x-pill :tone="$tone[$d->status] ?? 'slate'">{{ \App\Models\Deliverable::LABELS[$d->status] }}</x-pill></div>
            @if ($d->description)<p class="mt-1 text-slate-600">{{ $d->description }}</p>@endif
            @foreach ($d->files as $f)<a href="{{ route('files.show', $f) }}" target="_blank" class="mt-1 inline-flex items-center gap-1 text-xs text-brand-600 hover:underline"><i class="bi bi-paperclip"></i>{{ $f->original_name }}</a>@endforeach
            @foreach ($d->reviews as $r)
                <div class="mt-2 rounded-lg border-l-4 {{ $r->decision === 'approved' ? 'border-emerald-500' : 'border-orange-500' }} bg-slate-50 px-3 py-2 text-xs"><strong>Round {{ $r->round }}: {{ $r->decision === 'approved' ? 'Approved' : 'Changes requested' }}</strong> by {{ $r->user->name }} · {{ $r->created_at->format('d M, H:i') }}@if ($r->comment)<div class="mt-0.5 text-slate-700">{{ $r->comment }}</div>@endif</div>
            @endforeach
            @if ($d->status === 'changes_requested' && $canEdit)
                <form method="POST" action="{{ route('deliverables.resubmit', $d) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">@csrf<input type="file" name="files[]" multiple class="input w-auto text-xs" aria-label="Updated files"><button class="btn-primary btn-sm">Send back for review</button></form>
            @endif
        </div>
    @empty<p class="px-5 py-8 text-center text-sm text-slate-500">Nothing sent for review yet.</p>@endforelse
    @if ($canEdit && $project->client_id)
        <form method="POST" action="{{ route('projects.deliverables.store', $project) }}" enctype="multipart/form-data" class="space-y-3 border-t border-slate-100 p-5">@csrf
            <h3 class="text-sm font-semibold text-slate-900">Submit for review</h3>
            <div class="grid gap-3 sm:grid-cols-2"><input name="title" required class="input" placeholder="e.g. Keyword research report" aria-label="Title">
                <select name="milestone_id" class="input" aria-label="Milestone"><option value="">No milestone</option>@foreach ($milestones as $m)<option value="{{ $m->id }}">{{ $m->title }}</option>@endforeach</select></div>
            <textarea name="description" rows="2" class="input" placeholder="What should the client look at?" aria-label="Description"></textarea>
            <div class="flex flex-wrap items-center justify-between gap-2"><input type="file" name="files[]" multiple class="input w-auto text-xs" aria-label="Files"><button class="btn-primary btn-sm"><i class="bi bi-send"></i> Submit for review</button></div>
        </form>
    @endif
</div>
