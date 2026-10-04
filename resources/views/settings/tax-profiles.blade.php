@extends('layouts.app')
@section('title', 'Tax profiles')
@section('content')
<x-page-title title="Tax profiles" sub="Save your usual tax settings once and apply them to any invoice. Freelancy prints what you choose; it does not decide which tax applies to you." />
<div class="grid gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-2" aria-labelledby="tp"><div class="border-b border-slate-100 px-5 py-3"><h2 id="tp" class="font-semibold text-slate-900">Saved profiles</h2></div>
        @forelse ($profiles as $p)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
                <div class="min-w-0"><div class="font-medium text-slate-900">{{ $p->name }} @if ($p->is_default)<x-pill tone="green">Default</x-pill>@endif</div>
                    <div class="text-slate-500">{{ $treatments[$p->treatment] }}@if ($p->rate > 0) · {{ rtrim(rtrim(number_format($p->rate, 2), '0'), '.') }}%@endif · {{ ucfirst($p->applies_to) }}@if ($p->country_code) · {{ $countries[$p->country_code] ?? $p->country_code }}@endif</div></div>
                <form method="POST" action="{{ route('settings.tax-profiles.destroy', $p) }}" onsubmit="return confirm('Remove this tax profile?')">@csrf @method('DELETE')<button class="text-xs text-red-600 hover:underline">Remove</button></form>
            </div>
        @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No tax profiles yet. Add the combinations you use, like "GST 18% within India" or "Export under LUT".</p>@endforelse
    </section>
    <form method="POST" action="{{ route('settings.tax-profiles.store') }}" class="card h-fit space-y-3 p-5">@csrf
        <h2 class="font-semibold text-slate-900">New tax profile</h2>
        <div><label class="label" for="tp-name">Name</label><input id="tp-name" name="name" required class="input" placeholder="GST 18% within India"></div>
        <div><label class="label" for="tp-treat">Tax treatment</label><select id="tp-treat" name="treatment" class="input">@foreach ($treatments as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="label" for="tp-rate">Rate %</label><input id="tp-rate" name="rate" type="number" step="0.01" min="0" max="100" class="input"></div><div><label class="label" for="tp-label">Tax name</label><input id="tp-label" name="label" class="input" placeholder="VAT"></div></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="label" for="tp-ap">Used for</label><select id="tp-ap" name="applies_to" class="input"><option value="all">All invoices</option><option value="domestic">Domestic</option><option value="international">International</option></select></div>
            <div><label class="label" for="tp-cc">Country</label><select id="tp-cc" name="country_code" class="input"><option value="">Any</option>@foreach ($countries as $c => $n)<option value="{{ $c }}">{{ $n }}</option>@endforeach</select></div></div>
        <div class="grid grid-cols-2 gap-3"><div><label class="label" for="tp-pos">Place of supply</label><input id="tp-pos" name="place_of_supply" class="input"></div><div><label class="label" for="tp-sac">SAC code</label><input id="tp-sac" name="sac_code" class="input"></div></div>
        <div><label class="label" for="tp-lut">LUT reference</label><input id="tp-lut" name="lut_reference" class="input"></div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" class="rounded border-slate-300"> Use as my default</label>
        <button class="btn-primary w-full">Save profile</button>
    </form>
</div>
@endsection
