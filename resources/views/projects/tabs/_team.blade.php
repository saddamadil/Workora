    <div class="card">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Team</h2></div>
        <ul class="divide-y divide-slate-100">
            @foreach ($members as $m)
                <li class="flex items-center gap-3 px-5 py-3 text-sm">
                    <span class="grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-700">{{ strtoupper(substr($m->user->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1"><div class="truncate font-medium text-slate-900">{{ $m->user->name }}</div><div class="text-xs text-slate-500">{{ $m->role_in_project ?: 'Member' }}</div></div>
                    @can('manageMembers', $project)
                        <form method="POST" action="{{ route('projects.members.remove', [$project, $m->user_id]) }}" onsubmit="return confirm('Remove from this project?')">@csrf @method('DELETE')<button class="text-slate-400 hover:text-red-600" aria-label="Remove {{ $m->user->name }}"><i class="bi bi-x-lg"></i></button></form>
                    @endcan
                </li>
            @endforeach
        </ul>
        @can('manageMembers', $project)
            @if ($candidates->isNotEmpty())
                <form method="POST" action="{{ route('projects.members.add', $project) }}" class="flex flex-wrap gap-2 border-t border-slate-100 p-4">
                    @csrf
                    <select name="user_id" class="input min-w-0 flex-1" required aria-label="Person to add"><option value="">Add someone…</option>
                        @foreach ($candidates as $c)<option value="{{ $c->user_id }}">{{ $c->user->name }} ({{ $c->role->label() }})</option>@endforeach</select>
                    <input name="role_in_project" class="input w-36" placeholder="Role, e.g. Designer" aria-label="Role in project">
                    <label class="flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="can_view_budget" value="1" class="rounded border-slate-300"> sees budget</label>
                    <button class="btn-primary btn-sm">Add</button>
                </form>
            @endif
        @endcan
    </div>

