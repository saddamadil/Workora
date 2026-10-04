<div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-stat label="Tasks done" :value="$tasks->where('status', 'approved')->count().' / '.$tasks->where('status', '!=', 'cancelled')->count()" icon="bi-check2-square" tone="slate" />
    <x-stat :label="$role->isFreelancer() ? 'Your hours' : 'Hours logged'" :value="hours($minutes)" icon="bi-stopwatch" tone="green" />
    @if ($financials)
        <x-stat label="Budget" :value="$project->budget_minor ? money($project->budget_minor, $project->currency) : 'Not set'" icon="bi-wallet2" tone="slate" :hint="ucfirst($project->billing_model).' billing'" />
        <x-stat label="Cost so far (time)" :value="money($costMinor, $project->currency)" icon="bi-graph-up" :tone="$project->budget_minor && $costMinor > $project->budget_minor ? 'red' : 'amber'"
                :hint="$project->budget_minor ? round($costMinor / max(1, $project->budget_minor) * 100).'% of budget' : null" />
    @endif
</div>
@if ($project->description)<p class="card mb-6 whitespace-pre-line p-5 text-sm text-slate-700">{{ $project->description }}</p>@endif

<div class="grid gap-6 lg:grid-cols-2">
    <div class="space-y-6">
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Milestones</h2><a href="{{ route('projects.show', [$project, 'tab' => 'milestones']) }}" class="text-sm text-brand-600 hover:underline">Manage</a></div>
            @forelse ($milestones as $m)
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
                    <i class="bi {{ $m->status === 'completed' ? 'bi-check-circle-fill text-emerald-500' : ($m->status === 'in_progress' ? 'bi-play-circle-fill text-brand-500' : 'bi-circle text-slate-300') }} text-lg"></i>
                    <span class="min-w-0 flex-1 truncate text-slate-900">{{ $m->title }}</span><span class="text-slate-500">{{ \App\Models\ProjectMilestone::STATUSES[$m->status] }}</span>
                </div>
            @empty<p class="px-5 py-8 text-center text-sm text-slate-500">No milestones yet. Break the project into stages so your client can follow along.</p>@endforelse
        </div>
        @include('projects.tabs._activity-list', ['activity' => $activity])
    </div>
    <div class="space-y-6">
        @unless ($org->mode === 'solo') @include('projects.tabs._team') @endunless
        <div class="card">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Recent files</h2><a href="{{ route('projects.show', [$project, 'tab' => 'files']) }}" class="text-sm text-brand-600 hover:underline">All files</a></div>
            @include('projects.tabs._file-rows', ['files' => $files])
        </div>
    </div>
</div>
