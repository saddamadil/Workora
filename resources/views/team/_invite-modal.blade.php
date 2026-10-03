<div x-data="{ open: false, kind: 'freelancer' }" @open-invite.window="open = true" x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4">
    <form method="POST" action="{{ route('team.invite') }}" @click.outside="open = false" class="card w-full max-w-md space-y-4 p-6">
        @csrf
        <div class="flex items-start justify-between"><h2 class="text-lg font-bold text-slate-900">Invite someone</h2>
            <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <div class="grid grid-cols-2 gap-2">
            <label :class="kind === 'freelancer' ? 'border-brand-600 bg-brand-50' : 'border-slate-200'" class="cursor-pointer rounded-xl border p-3 text-center text-sm font-medium"><input type="radio" name="kind" value="freelancer" x-model="kind" class="sr-only">Freelancer</label>
            <label :class="kind === 'employee' ? 'border-brand-600 bg-brand-50' : 'border-slate-200'" class="cursor-pointer rounded-xl border p-3 text-center text-sm font-medium"><input type="radio" name="kind" value="employee" x-model="kind" class="sr-only">Company team member</label>
        </div>
        <div><label class="label" for="inv-email">Email</label><input id="inv-email" name="email" type="email" required class="input" placeholder="name@example.com"></div>
        <div x-show="kind === 'employee'"><label class="label" for="inv-role">Role</label>
            <select id="inv-role" name="role" class="input">@foreach (\App\Enums\OrganizationRole::staffRoles() as $r)@continue($r->value === 'owner' && $role?->value !== 'owner')<option value="{{ $r->value }}" @selected($r->value === 'team_member')>{{ $r->label() }}</option>@endforeach</select></div>
        <p class="text-xs text-slate-500">They get a link to join. If email is not set up on this server you can copy the link and send it yourself.</p>
        <button class="btn-primary w-full">Create invitation</button>
    </form>
</div>
