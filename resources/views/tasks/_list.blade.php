@if ($tasks->isEmpty())
    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ $empty ?? 'No tasks.' }}</p>
@else
    <ul class="divide-y divide-slate-100">
        @foreach ($tasks as $task)
            <li>
                <a href="{{ route('tasks.show', $task) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-slate-900">{{ $task->title }}</div>
                        <div class="truncate text-xs text-slate-500">
                            {{ $task->project->name }}
                            @if ($task->due_at) · <span class="{{ $task->isOverdue() ? 'font-semibold text-red-600' : '' }}">due {{ $task->due_at->format('d M') }}</span> @endif
                            @if ($task->relationLoaded('assignees') && $task->assignees->isNotEmpty()) · {{ $task->assignees->pluck('name')->join(', ') }} @endif
                        </div>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ \App\Models\Task::STATUS_STYLES[$task->status] ?? '' }}">{{ $task->statusLabel() }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
