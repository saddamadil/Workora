@php $tone = ['active' => 'green', 'planning' => 'blue', 'on_hold' => 'amber', 'completed' => 'slate', 'cancelled' => 'slate']; @endphp
<a href="{{ route('portal.project', $p->slug) }}" class="card block p-5 transition hover:border-slate-300">
    <div class="flex items-start justify-between gap-3">
        <h3 class="min-w-0 truncate font-semibold text-slate-900">{{ $p->name }}</h3>
        <x-pill :tone="$tone[$p->status] ?? 'slate'">{{ \App\Models\Project::STATUS_LABELS[$p->status] ?? $p->status }}</x-pill>
    </div>
    <div class="mt-3 flex items-center gap-3">
        <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $p->progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress"><div class="h-full rounded-full bg-brand-500" style="width: {{ $p->progress }}%"></div></div>
        <span class="text-sm font-semibold text-slate-900">{{ $p->progress }}%</span>
    </div>
    @if ($p->deadline)<p class="mt-2 text-sm text-slate-500">Deadline {{ $p->deadline->format('d M Y') }}</p>@endif
</a>
