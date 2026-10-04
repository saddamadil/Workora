@extends('layouts.app')
@section('title', $quote->exists ? 'Edit quote' : 'New quote')
@section('content')
@php
    $rows = old('items') ?: collect($quote->items)->map(fn ($i) => ['description' => $i['description'], 'quantity' => $i['quantity'], 'unit_rate' => \App\Support\Money::toInput($i['unit_rate_minor'])])->all();
@endphp
<x-page-title :title="$quote->exists ? 'Edit '.$quote->number : 'New quote'" sub="A priced offer your client can accept. Accepting it starts a project." />
<form method="POST" action="{{ $quote->exists ? route('quotes.update', $quote) : route('quotes.store') }}" class="space-y-6">@csrf @if ($quote->exists) @method('PUT') @endif
    <div class="card space-y-4 p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label" for="client_id">Client</label><select id="client_id" name="client_id" required class="input"><option value="">Choose a client</option>@foreach ($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $quote->client_id) === $c->id)>{{ $c->name }}</option>@endforeach</select>@error('client_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="title">Title (becomes the project name)</label><input id="title" name="title" required maxlength="160" value="{{ old('title', $quote->title) }}" class="input">@error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
            <div><label class="label" for="currency">Currency</label><select id="currency" name="currency" class="input">@foreach ($currencies as $c)<option @selected(old('currency', $quote->currency) === $c)>{{ $c }}</option>@endforeach</select></div>
            <div><label class="label" for="valid_until">Valid until</label><input id="valid_until" type="date" name="valid_until" min="{{ now()->toDateString() }}" value="{{ old('valid_until', $quote->valid_until?->toDateString()) }}" class="input">@error('valid_until')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        </div>
        <div><label class="label" for="intro">Scope and introduction</label><textarea id="intro" name="intro" rows="4" maxlength="5000" class="input">{{ old('intro', $quote->intro) }}</textarea></div>
    </div>
    <div class="card space-y-4 p-5"><h2 class="font-semibold text-slate-900">Lines</h2>
        @include('quotes._items', ['rows' => $rows])
        @error('items')<p class="text-sm text-red-600">{{ $message }}</p>@enderror @error('items.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <div class="grid gap-4 sm:grid-cols-2"><div><label class="label" for="tax_label">Tax label (optional)</label><input id="tax_label" name="tax_label" maxlength="30" value="{{ old('tax_label', $quote->tax_label) }}" placeholder="e.g. GST" class="input"></div>
            <div><label class="label" for="tax_rate">Tax rate % (you decide what applies)</label><input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" value="{{ old('tax_rate', $quote->tax_rate ?: '') }}" class="input"></div></div>
    </div>
    <div class="card p-5"><label class="label" for="terms">Terms</label><textarea id="terms" name="terms" rows="4" maxlength="5000" class="input" placeholder="Payment schedule, revisions included, timeline...">{{ old('terms', $quote->terms) }}</textarea></div>
    <div class="flex gap-2"><button class="btn-primary">Save draft</button><a href="{{ $quote->exists ? route('quotes.show', $quote) : route('quotes.index') }}" class="btn-secondary">Cancel</a></div>
</form>
@endsection
