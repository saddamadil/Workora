<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-slate-600">Total logged on this project: <strong class="text-slate-900">{{ hours($minutes) }}</strong>
        @if ($project->share_hours)<span class="ms-2 rounded bg-sky-50 px-2 py-0.5 text-xs text-sky-700">Visible to the client</span>@endif</p>
    @can('track-time')<a href="{{ route('time.index') }}" class="btn-primary btn-sm"><i class="bi bi-stopwatch"></i> Log work</a>@endcan
</div>
<div class="card overflow-x-auto">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Date</th><th class="px-3 py-3">Task</th><th class="px-3 py-3">Note</th><th class="px-3 py-3 text-right">Time</th><th class="px-5 py-3 text-right">Billable</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($entries as $e)
                <tr><td class="whitespace-nowrap px-5 py-2.5">{{ $e->entry_date->format('d M') }}</td><td class="px-3 py-2.5">{{ $e->task?->title ?: '—' }}</td><td class="px-3 py-2.5 text-slate-600">{{ $e->description }}</td>
                    <td class="whitespace-nowrap px-3 py-2.5 text-right">{{ hours($e->minutes) }}</td><td class="px-5 py-2.5 text-right">{{ $e->is_billable ? 'Billable' : 'Non-billable' }}</td></tr>
            @empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No time logged yet.</td></tr>@endforelse
        </tbody>
    </table>
</div>
