@extends('layouts.app')
@section('title', $agreement->title)
@section('content')
<div class="mb-2 text-sm"><a href="{{ route('portal.agreements') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Agreements</a></div>
<x-page-title :title="$agreement->title"><a href="{{ route('portal.agreements.pdf', $agreement) }}" target="_blank" class="btn-secondary btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a></x-page-title>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-6 lg:col-span-2"><div class="whitespace-pre-line text-sm leading-relaxed text-slate-800">{{ $agreement->body }}</div></div>
    <div class="space-y-4">
        @include('agreements._record')
        @if ($agreement->status === 'sent')
            @if (app(\App\Support\Tenancy::class)->isClientOwner())
            <form method="POST" action="{{ route('portal.agreements.sign', $agreement) }}" class="card space-y-3 p-5 text-sm">@csrf
                <h2 class="font-semibold text-slate-900">Sign</h2>
                <div><label class="label" for="sn">Type your full name</label><input id="sn" name="signed_name" required maxlength="120" class="input">@error('signed_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <label class="flex items-start gap-2"><input type="checkbox" name="agree" value="1" required class="mt-1 rounded border-slate-300"><span>I have read this agreement and agree to it. I understand typing my name is my signature.</span></label>
                <button class="btn-primary w-full">Sign agreement</button></form>
            <form method="POST" action="{{ route('portal.agreements.decline', $agreement) }}" class="card space-y-2 p-5 text-sm">@csrf<input name="reason" maxlength="250" placeholder="Reason (optional)" class="input" aria-label="Reason"><button class="btn-secondary w-full">Decline</button></form>
            @else<p class="text-sm text-slate-600">Only the account owner can sign.</p>@endif
        @endif
    </div>
</div>
@endsection
