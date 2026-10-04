@extends('layouts.app')
@section('title', 'Plan')
@section('content')
<x-page-title title="Your plan" sub="What your workspace includes." />
<div class="card max-w-2xl p-6">
    <div class="flex items-center justify-between"><div><div class="text-xl font-bold text-slate-900">{{ $plan?->name ?? 'Unlimited' }}</div><div class="text-sm text-slate-500">{{ $plan && $plan->price_minor ? money($plan->price_minor, $plan->currency).' per '.($plan->interval === 'yearly' ? 'year' : 'month') : 'No charge' }}</div></div><x-pill tone="green">Active</x-pill></div>
    <dl class="mt-5 space-y-3">
        @foreach ($labels as $key => $label)
            @php $cap = $limits->limit($key); $used = $usage[$key]; $pct = $cap ? min(100, round($used / $cap * 100)) : 0; @endphp
            <div><div class="flex justify-between text-sm"><dt class="text-slate-700">{{ ucfirst($label) }}</dt><dd class="font-medium text-slate-900">{{ $used }} {{ $cap ? 'of '.$cap : '(no limit)' }}</dd></div>
                @if ($cap)<div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ ucfirst($label) }} used"><div class="h-full rounded-full {{ $pct >= 90 ? 'bg-red-500' : 'bg-brand-500' }}" style="width: {{ $pct }}%"></div></div>@endif</div>
        @endforeach
    </dl>
    <p class="mt-5 text-sm text-slate-500">To change your plan, ask the person who runs this Freelancy site.</p>
</div>
@endsection
