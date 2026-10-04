@extends('layouts.app')
@section('title', 'Plans')
@section('content')
<x-page-title title="Workspace plans" sub="Plans only set limits. There is no billing in Freelancy." />
<div class="card divide-y divide-slate-100">
    @foreach ($orgs as $o)
        <form method="POST" action="{{ route('admin.plans.set', $o) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">@csrf
            <span class="min-w-0 font-medium text-slate-900">{{ $o->name }} <span class="font-normal text-slate-500">· {{ $o->mode }}</span></span>
            <span class="flex items-center gap-2"><label class="sr-only" for="p-{{ $o->id }}">Plan for {{ $o->name }}</label>
                <select id="p-{{ $o->id }}" name="plan_id" class="input w-auto py-1.5">@foreach ($plans as $p)<option value="{{ $p->id }}" @selected(($subs[$o->id]->plan_id ?? null) === $p->id)>{{ $p->name }}</option>@endforeach</select><button class="btn-secondary btn-sm">Save</button></span>
        </form>
    @endforeach
</div>
@endsection
