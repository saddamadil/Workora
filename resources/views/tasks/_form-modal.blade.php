<div x-data="{ open: false }" @new-task.window="open = true" x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-slate-900/40 p-4">
    <form method="POST" action="{{ route('tasks.store', $project) }}" @click.outside="open = false" class="card my-8 w-full max-w-lg space-y-4 p-6">
        @csrf
        <div class="flex items-start justify-between"><h2 class="text-lg font-bold text-slate-900">New task</h2>
            <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <div><label class="label" for="t-title">Title</label><input id="t-title" name="title" required class="input" placeholder="What needs to be done?"></div>
        <div><label class="label" for="t-desc">Details</label><textarea id="t-desc" name="description" rows="3" class="input"></textarea></div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="t-pri">Priority</label>
                <select id="t-pri" name="priority" class="input">@foreach (['low', 'medium', 'high', 'urgent'] as $p)<option value="{{ $p }}" @selected($p === 'medium')>{{ ucfirst($p) }}</option>@endforeach</select></div>
            <div><label class="label" for="t-due">Due date</label><input id="t-due" type="date" name="due_at" class="input"></div>
            <div><label class="label" for="t-est">Estimated hours</label><input id="t-est" type="number" step="0.25" min="0" name="estimated_hours" class="input"></div>
            @if (Gate::allows('see-money'))<div><label class="label" for="t-bud">Task budget ({{ $project->currency }})</label><input id="t-bud" type="number" step="0.01" min="0" name="budget" class="input"></div>@endif
        </div>
        <fieldset><legend class="label">Assign to</legend>
            <div class="max-h-40 space-y-1 overflow-y-auto rounded-xl border border-slate-200 p-2">
                @forelse ($people as $u)<label class="flex items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-slate-50"><input type="checkbox" name="assignee_ids[]" value="{{ $u->id }}" class="rounded border-slate-300"> {{ $u->name }}</label>
                @empty<p class="p-2 text-sm text-slate-500">Add people to the project first.</p>@endforelse
            </div></fieldset>
        <button class="btn-primary w-full">Create task</button>
    </form>
</div>
