@extends('layouts.app')
@section('title', 'Expenses')
@section('content')
<x-page-title title="Expenses" sub="Record what you spend and keep the receipt. Mark an expense billable to add it to a client's invoice at cost." />
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div><label class="label" for="em">Month</label><input id="em" type="month" name="month" value="{{ $month }}" class="input" onchange="this.form.submit()"></div>
            <div><label class="label" for="ec">Category</label><select id="ec" name="category" class="input" onchange="this.form.submit()"><option value="">All</option>@foreach ($categories as $c)<option @selected($category === $c)>{{ $c }}</option>@endforeach</select></div>
            @if ($month || $category)<a href="{{ route('expenses.index') }}" class="btn-secondary btn-sm">Clear</a>@endif
        </form>
        @if ($byCurrency->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-3">@foreach ($byCurrency as $cur => $minor)<div class="card p-4"><p class="text-sm text-slate-500">Total ({{ $cur }})</p><p class="text-xl font-bold text-slate-900">{{ money($minor, $cur) }}</p></div>@endforeach</div>
        @endif
        <div class="card">
            @forelse ($expenses as $e)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
                    <span><strong class="text-slate-900">{{ $e->description }}</strong><span class="block text-slate-500">{{ $e->spent_on->format('d M Y') }} · {{ $e->category }}@if ($e->client) · {{ $e->client->name }}@endif @if ($e->project) / {{ $e->project->name }}@endif
                        @if ($e->receipt) · <a href="{{ route('files.show', $e->receipt_file_id) }}" target="_blank" class="text-brand-600 hover:underline"><i class="bi bi-paperclip"></i> Receipt</a>@endif</span></span>
                    <span class="flex items-center gap-3"><strong>{{ money($e->amount_minor, $e->currency) }}</strong>
                        @if ($e->billed_invoice_id)<x-pill tone="green">Billed</x-pill>@elseif ($e->is_billable)<x-pill tone="amber">Billable</x-pill>@endif
                        @unless ($e->billed_invoice_id)<form method="POST" action="{{ route('expenses.destroy', $e) }}" onsubmit="return confirm('Delete this expense?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Delete</button></form>@endunless</span>
                </div>
            @empty<p class="px-5 py-12 text-center text-sm text-slate-500">No expenses recorded.</p>@endforelse
        </div>
    </div>
    <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="card h-fit space-y-3 p-5" x-data="{ clientId: '{{ old('client_id') }}', projects: @js($projects) }">@csrf
        <h2 class="font-semibold text-slate-900">Add an expense</h2>
        <div class="grid grid-cols-2 gap-3"><div><label class="label" for="ex-d">Date</label><input id="ex-d" type="date" name="spent_on" value="{{ old('spent_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required class="input"></div>
            <div><label class="label" for="ex-c">Category</label><select id="ex-c" name="category" class="input">@foreach ($categories as $c)<option @selected(old('category') === $c)>{{ $c }}</option>@endforeach</select></div></div>
        <div><label class="label" for="ex-t">What was it</label><input id="ex-t" name="description" required maxlength="200" value="{{ old('description') }}" class="input"></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="label" for="ex-a">Amount</label><input id="ex-a" name="amount" type="number" step="0.01" min="0.01" required value="{{ old('amount') }}" class="input"></div>
            <div><label class="label" for="ex-cu">Currency</label><select id="ex-cu" name="currency" class="input">@foreach ($currencies as $c)<option @selected(old('currency', 'INR') === $c)>{{ $c }}</option>@endforeach</select></div></div>
        <div><label class="label" for="ex-cl">Client (optional)</label><select id="ex-cl" name="client_id" x-model="clientId" class="input"><option value="">None</option>@foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>@error('client_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="ex-p">Project (optional)</label><select id="ex-p" name="project_id" class="input"><option value="">None</option><template x-for="p in projects.filter(p => p.client_id === clientId)" :key="p.id"><option :value="p.id" x-text="p.name"></option></template></select></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_billable" value="1" @checked(old('is_billable'))> Bill this to the client</label>
        <div><label class="label" for="ex-r">Receipt (PDF or image)</label><input id="ex-r" type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp" class="input">@error('receipt')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <button class="btn-primary w-full">Save expense</button>
    </form>
</div>
@endsection
