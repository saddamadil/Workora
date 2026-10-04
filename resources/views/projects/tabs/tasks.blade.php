<div class="mb-3 flex items-center justify-between">
    <h2 class="text-lg font-bold text-slate-900">Tasks</h2>
    @if ($canCreateTask)<button type="button" class="btn-primary btn-sm" @click="$dispatch('new-task')"><i class="bi bi-plus-lg"></i> Add task</button>@endif
</div>

<div class="mb-8 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
    @foreach ($columns as $title => $statuses)
        @php $col = $tasks->whereIn('status', $statuses); @endphp
        <div class="rounded-2xl bg-slate-100/70 p-3">
            <div class="mb-2 flex items-center justify-between px-1 text-sm font-semibold text-slate-700">{{ $title }} <span class="rounded-full bg-white px-2 text-xs text-slate-500">{{ $col->count() }}</span></div>
            <div class="space-y-2">
                @forelse ($col as $t)
                    <a href="{{ route('tasks.show', $t) }}" class="block rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-brand-500">
                        <div class="text-sm font-medium text-slate-900">{{ $t->title }}</div>
                        <div class="mt-1.5 flex items-center justify-between text-xs text-slate-500">
                            <span class="{{ $t->isOverdue() ? 'font-semibold text-red-600' : '' }}">{{ $t->due_at ? $t->due_at->format('d M') : '' }}</span>
                            <span class="truncate">{{ $t->assignees->pluck('name')->map(fn ($n) => explode(' ', $n)[0])->join(', ') ?: 'Unassigned' }}</span>
                        </div>
                    </a>
                @empty
                    <p class="px-1 py-3 text-center text-xs text-slate-400">Empty</p>
                @endforelse
            </div>
        </div>
    @endforeach
</div>

