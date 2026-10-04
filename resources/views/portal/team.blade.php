@extends('layouts.app')
@section('title', 'Our team')
@section('content')
<x-page-title title="Our team" sub="Colleagues who can sign in to this portal for your company." />
<div class="grid gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-2" aria-labelledby="mm"><div class="border-b border-slate-100 px-5 py-3"><h2 id="mm" class="font-semibold text-slate-900">People with access</h2></div>
        @foreach ($members as $m)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
                <span class="min-w-0"><span class="font-medium text-slate-900">{{ $m->user->name }}</span> <span class="text-slate-500">· {{ $m->user->email }}</span></span>
                <span class="flex items-center gap-3"><x-pill :tone="$m->role->isClientOwner() ? 'green' : 'slate'">{{ $m->role->isClientOwner() ? 'Main contact' : 'Member' }}</x-pill>
                    @if ($isOwner && ! $m->role->isClientOwner())<form method="POST" action="{{ route('portal.team.remove', $m) }}" onsubmit="return confirm('Remove this person\'s access?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Remove</button></form>@endif</span>
            </div>
        @endforeach
        @foreach ($invitations as $i)<div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 text-sm last:border-0"><span class="text-slate-600">{{ $i->email }}</span><x-pill tone="amber">Invitation pending</x-pill></div>@endforeach
    </section>
    @if ($isOwner)
    <form method="POST" action="{{ route('portal.team.invite') }}" class="card h-fit space-y-3 p-5">@csrf
        <h2 class="font-semibold text-slate-900">Invite a colleague</h2>
        <p class="text-xs text-slate-500">Members can see projects, files and invoices, message your freelancer and send requests. Only you can approve work, report payments and change company details.</p>
        <div><label class="label" for="em">Email</label><input id="em" name="email" type="email" required class="input">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <button class="btn-primary w-full">Send invitation</button>
    </form>
    @endif
</div>
@endsection
