@extends('layouts.app')
@section('title', 'Clients')
@section('content')
@php $can = Gate::allows('manage-clients'); @endphp
<x-page-title title="Clients" sub="The people and companies you do the work for." />

@if ($can)
    <form method="POST" action="{{ route('clients.store') }}" class="card mb-6 grid gap-3 p-5 sm:grid-cols-4">
        @csrf
        <div class="sm:col-span-2"><label class="label" for="c-name">Client name</label><input id="c-name" name="name" required class="input" placeholder="e.g. Northwind Ltd"></div>
        <div><label class="label" for="c-contact">Contact person</label><input id="c-contact" name="contact_name" class="input"></div>
        <div><label class="label" for="c-email">Email</label><input id="c-email" name="email" type="email" class="input"></div>
        <div class="sm:col-span-4 flex justify-end"><button class="btn-primary"><i class="bi bi-plus-lg"></i> Add client</button></div>
    </form>
@endif

<div class="card divide-y divide-slate-100">
    @forelse ($clients as $c)
        <div class="flex flex-wrap items-center gap-3 px-5 py-4" x-data="{ edit: false }">
            <span class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-600"><i class="bi bi-building"></i></span>
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-slate-900">{{ $c->name }}</div>
                <div class="truncate text-sm text-slate-500">{{ collect([$c->contact_name, $c->email, $c->phone])->filter()->join(' · ') ?: 'No contact details' }}</div>
            </div>
            <span class="text-sm text-slate-500">{{ $c->projects_count }} project{{ $c->projects_count == 1 ? '' : 's' }}</span>
            @if ($can)
                <button type="button" class="btn-secondary btn-sm" @click="edit = !edit"><i class="bi bi-pencil"></i> Edit</button>
                <form method="POST" action="{{ route('clients.destroy', $c) }}" onsubmit="return confirm('Remove this client? Their projects are kept.')">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600" aria-label="Remove {{ $c->name }}"><i class="bi bi-trash"></i></button></form>
                <form method="POST" action="{{ route('clients.update', $c) }}" x-show="edit" x-cloak class="mt-2 grid w-full gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-4">
                    @csrf @method('PATCH')
                    <div class="sm:col-span-2"><label class="label">Name</label><input name="name" value="{{ $c->name }}" required class="input"></div>
                    <div><label class="label">Contact</label><input name="contact_name" value="{{ $c->contact_name }}" class="input"></div>
                    <div><label class="label">Email</label><input name="email" type="email" value="{{ $c->email }}" class="input"></div>
                    <div><label class="label">Phone</label><input name="phone" value="{{ $c->phone }}" class="input"></div>
                    <div class="sm:col-span-2"><label class="label">Notes</label><input name="notes" value="{{ $c->notes }}" class="input"></div>
                    <div class="flex items-end"><button class="btn-primary w-full">Save</button></div>
                </form>
            @endif
        </div>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-500">No clients yet. Add the first one above.</p>
    @endforelse
</div>
@endsection
