@extends('layouts.app')
@section('title', 'New invoice')
@section('content')
<x-page-title title="New invoice" sub="Invoices belong to a contract, so the rate and payment terms are filled in for you." />
@if ($contracts->isEmpty())
    <div class="card max-w-xl p-8 text-center"><i class="bi bi-file-earmark-ruled text-4xl text-slate-300"></i>
        <p class="mt-3 font-semibold text-slate-700">You need an active contract to invoice.</p>
        <p class="mt-1 text-sm text-slate-500">Ask the company to send you one, then accept it from <a class="text-brand-600 hover:underline" href="{{ route('contracts.index') }}">Contracts</a>.</p></div>
@else
    <form method="POST" action="{{ route('invoices.store') }}" class="card max-w-xl space-y-4 p-6">@csrf
        <div><label class="label" for="contract_id">Which contract?</label>
            <select id="contract_id" name="contract_id" class="input" required>@foreach ($contracts as $c)<option value="{{ $c->id }}">{{ $c->title }} ({{ $c->reference }})</option>@endforeach</select></div>
        <button class="btn-primary w-full">Create draft</button>
    </form>
@endif
@endsection
