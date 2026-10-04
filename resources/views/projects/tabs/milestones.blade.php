<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Milestones</h2></div>
            @forelse ($milestones as $m)
                <form method="POST" action="{{ route('milestones.update', $m) }}" class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">@csrf @method('PATCH')
                    <i class="bi {{ $m->status === 'completed' ? 'bi-check-circle-fill text-emerald-500' : ($m->status === 'in_progress' ? 'bi-play-circle-fill text-brand-500' : 'bi-circle text-slate-300') }} text-xl"></i>
                    <div class="min-w-0 flex-1"><div class="font-medium text-slate-900">{{ $m->title }}</div><div class="text-xs text-slate-500">{{ $m->due_date ? 'Due '.$m->due_date->format('d M Y') : 'No date' }}</div></div>
                    @if ($canEdit)
                        <select name="status" class="input w-auto py-1.5" aria-label="Status of {{ $m->title }}" onchange="this.form.submit()">@foreach (\App\Models\ProjectMilestone::STATUSES as $k => $l)<option value="{{ $k }}" @selected($m->status === $k)>{{ $l }}</option>@endforeach</select>
                        <button form="del-{{ $m->id }}" class="text-slate-400 hover:text-red-600" aria-label="Remove {{ $m->title }}"><i class="bi bi-x-lg"></i></button>
                    @else<x-pill>{{ \App\Models\ProjectMilestone::STATUSES[$m->status] }}</x-pill>@endif
                </form>
                @if ($canEdit)<form id="del-{{ $m->id }}" method="POST" action="{{ route('milestones.destroy', $m) }}" class="hidden">@csrf @method('DELETE')</form>@endif
            @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No milestones yet. Add stages like "Technical audit" or "First draft" so progress is visible.</p>@endforelse
        </div>
        @include('projects.tabs._deliverables')
    </div>
    @if ($canEdit)
    <form method="POST" action="{{ route('projects.milestones.store', $project) }}" class="card h-fit space-y-3 p-5">@csrf
        <h2 class="font-semibold text-slate-900">Add milestone</h2>
        <div><label class="label" for="ms-title">Title</label><input id="ms-title" name="title" required class="input"></div>
        <div><label class="label" for="ms-due">Due date</label><input id="ms-due" type="date" name="due_date" class="input"></div>
        <button class="btn-primary w-full">Add</button>
    </form>
    @endif
</div>
