@extends('layouts.app')
@section('title', $contract->exists ? 'Edit contract' : 'New contract')
@section('content')
<x-page-title :title="$contract->exists ? 'Edit contract' : 'New contract'" />
<form method="POST" action="{{ $contract->exists ? route('contracts.update', $contract) : route('contracts.store') }}" class="card max-w-3xl space-y-5 p-6" x-data="{ type: '{{ old('type', $contract->type) }}' }">
    @csrf @if ($contract->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="user_id">Freelancer</label>
            <select id="user_id" name="user_id" required class="input"><option value="">Choose…</option>@foreach ($freelancers as $f)<option value="{{ $f->user_id }}" @selected(old('user_id', $contract->user_id) === $f->user_id)>{{ $f->user->name }}</option>@endforeach</select>
            @if ($freelancers->isEmpty())<p class="mt-1 text-xs text-slate-500">Invite a freelancer from <a class="text-brand-600 hover:underline" href="{{ route('team.index') }}">Team</a> first.</p>@endif</div>
        <div><label class="label" for="project_id">Project <span class="font-normal text-slate-400">(optional)</span></label>
            <select id="project_id" name="project_id" class="input"><option value="">Any project</option>@foreach ($projects as $p)<option value="{{ $p->id }}" @selected(old('project_id', $contract->project_id) === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
    </div>
    <div><label class="label" for="title">Title</label><input id="title" name="title" value="{{ old('title', $contract->title) }}" required class="input" placeholder="e.g. Website redesign, Q4"></div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="label" for="type">How are they paid?</label>
            <select id="type" name="type" x-model="type" class="input">
                <option value="hourly">Hourly</option><option value="fixed">Fixed price</option><option value="milestone">By milestone</option><option value="retainer">Monthly retainer</option></select></div>
        <div><label class="label" for="currency">Currency</label>
            <select id="currency" name="currency" class="input">@foreach (array_keys(\App\Support\Money::CURRENCIES) as $c)<option @selected(old('currency', $contract->currency) === $c)>{{ $c }}</option>@endforeach</select></div>
        <div x-show="type === 'hourly'"><label class="label" for="hourly_rate">Hourly rate</label><input id="hourly_rate" name="hourly_rate" type="number" step="0.01" min="0" value="{{ old('hourly_rate', \App\Support\Money::toInput($contract->type === 'hourly' ? $contract->hourly_rate_minor : null)) }}" class="input"></div>
        <div x-show="type === 'fixed'" x-cloak><label class="label" for="fixed_amount">Total price</label><input id="fixed_amount" name="fixed_amount" type="number" step="0.01" min="0" value="{{ old('fixed_amount', \App\Support\Money::toInput($contract->fixed_amount_minor)) }}" class="input"></div>
        <div x-show="type === 'retainer'" x-cloak><label class="label" for="retainer_amount">Retainer per cycle</label><input id="retainer_amount" name="retainer_amount" type="number" step="0.01" min="0" value="{{ old('retainer_amount', \App\Support\Money::toInput($contract->retainer_amount_minor)) }}" class="input"></div>
    </div>
    <div x-show="type === 'retainer'" x-cloak class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="max_hours_per_cycle">Hours included per cycle</label><input id="max_hours_per_cycle" name="max_hours_per_cycle" type="number" step="0.5" min="0" value="{{ old('max_hours_per_cycle', $contract->max_hours_per_cycle) }}" class="input"></div>
        <div><label class="label" for="hourly_rate_retainer">Rate for extra hours</label><input id="hourly_rate_retainer" name="hourly_rate_retainer" type="number" step="0.01" min="0" value="{{ old('hourly_rate_retainer', \App\Support\Money::toInput($contract->type === 'retainer' ? $contract->hourly_rate_minor : null)) }}" class="input"></div>
    </div>
    <p x-show="type === 'milestone'" x-cloak class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">You will add the milestones and their amounts on the next screen.</p>
    <div class="grid gap-4 sm:grid-cols-3">
        <div><label class="label" for="payment_cycle">Invoice</label>
            <select id="payment_cycle" name="payment_cycle" class="input">@foreach (['weekly' => 'Weekly', 'biweekly' => 'Every 2 weeks', 'monthly' => 'Monthly', 'on_completion' => 'On completion'] as $k => $l)<option value="{{ $k }}" @selected(old('payment_cycle', $contract->payment_cycle) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="label" for="payment_terms_days">Pay within (days)</label><input id="payment_terms_days" type="number" min="0" max="180" name="payment_terms_days" value="{{ old('payment_terms_days', $contract->payment_terms_days) }}" class="input"></div>
        <div></div>
        <div><label class="label" for="starts_on">Starts</label><input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on', $contract->starts_on?->format('Y-m-d')) }}" required class="input"></div>
        <div><label class="label" for="ends_on">Ends <span class="font-normal text-slate-400">(optional)</span></label><input id="ends_on" type="date" name="ends_on" value="{{ old('ends_on', $contract->ends_on?->format('Y-m-d')) }}" class="input"></div>
    </div>
    <div><label class="label" for="terms">Terms</label><textarea id="terms" name="terms" rows="6" class="input" placeholder="Scope, ownership of work, confidentiality, notice period…">{{ old('terms', $contract->terms) }}</textarea></div>
    <div class="flex justify-end gap-2"><a href="{{ $contract->exists ? route('contracts.show', $contract) : route('contracts.index') }}" class="btn-secondary">Cancel</a><button class="btn-primary">Save draft</button></div>
</form>
@endsection
