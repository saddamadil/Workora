@extends('layouts.guest')
@section('title', 'Welcome')
@section('width', 'max-w-xl')
@section('content')
<ol class="mb-5 flex justify-center gap-1.5" aria-label="Setup progress">@foreach ($steps as $n => $l)<li class="h-1.5 w-10 rounded-full {{ $n <= $step ? 'bg-brand-500' : 'bg-slate-200' }}" @if ($n === $step) aria-current="step" @endif><span class="sr-only">{{ $l }}</span></li>@endforeach</ol>
<div class="card p-7">
@if ($step === 1)
    <h1 class="text-2xl font-bold text-slate-900">Welcome to Freelancy</h1>
    <p class="mt-2 text-slate-600">Manage your clients, projects, work and payments in one simple workspace. Setting up takes about two minutes, and every step can be skipped.</p>
    <div class="mt-6 flex gap-2"><a href="{{ route('welcome', 2) }}" class="btn-primary flex-1">Get started</a><form method="POST" action="{{ route('welcome.done') }}">@csrf<button class="btn-secondary">Skip setup</button></form></div>
@elseif ($step === 2)
    <h1 class="text-xl font-bold text-slate-900">Create your profile</h1><p class="mt-1 text-sm text-slate-500">This appears on your invoices. You can add more later.</p>
    <form method="POST" action="{{ route('welcome.profile') }}" class="mt-5 space-y-4">@csrf
        <div><label class="label" for="headline">Professional title</label><input id="headline" name="headline" value="{{ old('headline', $profile?->headline) }}" class="input" placeholder="e.g. SEO consultant" autofocus></div>
        <div class="grid gap-4 sm:grid-cols-2"><div><label class="label" for="country_code">Country</label><select id="country_code" name="country_code" class="input"><option value="">Choose</option>@foreach ($countries as $c => $n)<option value="{{ $c }}" @selected(old('country_code', $profile?->country_code) === $c)>{{ $n }}</option>@endforeach</select></div>
            <div><label class="label" for="city">City</label><input id="city" name="city" value="{{ old('city', $profile?->city) }}" class="input"></div></div>
        <div class="flex gap-2"><button class="btn-primary flex-1">Continue</button><a href="{{ route('welcome', 3) }}" class="btn-secondary">Skip</a></div>
    </form>
@elseif ($step === 3)
    <h1 class="text-xl font-bold text-slate-900">How do you get paid?</h1><p class="mt-1 text-sm text-slate-500">Saved once and used on every invoice. International accounts can be added later in Payment profiles.</p>
    <form method="POST" action="{{ route('welcome.payment') }}" class="mt-5 space-y-4">@csrf
        @if ($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-800">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <input type="hidden" name="currency" value="INR"><input type="hidden" name="country_code" value="IN">
        <div class="grid gap-4 sm:grid-cols-2"><div><label class="label" for="account_holder">Account holder</label><input id="account_holder" name="account_holder" value="{{ old('account_holder', auth()->user()->name) }}" required class="input"></div>
            <div><label class="label" for="bank_name">Bank</label><input id="bank_name" name="bank_name" value="{{ old('bank_name') }}" class="input"></div>
            <div><label class="label" for="account_number">Account number</label><input id="account_number" name="account_number" value="{{ old('account_number') }}" class="input" inputmode="numeric"></div>
            <div><label class="label" for="ifsc">IFSC</label><input id="ifsc" name="ifsc" value="{{ old('ifsc') }}" class="input uppercase"></div>
            <div class="sm:col-span-2"><label class="label" for="upi_id">or UPI ID</label><input id="upi_id" name="upi_id" value="{{ old('upi_id') }}" class="input" placeholder="name@bank"></div></div>
        <div class="flex gap-2"><button class="btn-primary flex-1">Save and continue</button><a href="{{ route('welcome', 4) }}" class="btn-secondary">Skip</a></div>
    </form>
@elseif ($step === 4)
    <h1 class="text-xl font-bold text-slate-900">Add your first client</h1><p class="mt-1 text-sm text-slate-500">You can invite them to their own portal afterwards.</p>
    <form method="POST" action="{{ route('welcome.client') }}" class="mt-5 space-y-4">@csrf
        @if (session('duplicate'))<div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900"><strong>This client already exists.</strong> <a class="underline" href="{{ route('clients.show', session('duplicate')['id']) }}">Open existing client</a></div>@endif
        <div><label class="label" for="name">Client or company name</label><input id="name" name="name" value="{{ old('name') }}" required class="input" autofocus>@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <div><label class="label" for="email">Email <span class="font-normal text-slate-400">(needed to invite them)</span></label><input id="email" name="email" type="email" value="{{ old('email') }}" class="input">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
        <input type="hidden" name="type" value="company">
        <div class="flex gap-2"><button class="btn-primary flex-1">Add client</button><a href="{{ route('welcome', 6) }}" class="btn-secondary">Skip</a></div>
    </form>
@elseif ($step === 5)
    <h1 class="text-xl font-bold text-slate-900">Create your first project</h1><p class="mt-1 text-sm text-slate-500">Projects keep a client's work organized.</p>
    <form method="POST" action="{{ route('welcome.project') }}" class="mt-5 space-y-4">@csrf
        @if ($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-800">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <div><label class="label" for="pname">Project name</label><input id="pname" name="name" value="{{ old('name') }}" required class="input" placeholder="e.g. SEO campaign" autofocus></div>
        <div><label class="label" for="client_id">Client</label><select id="client_id" name="client_id" class="input"><option value="">No client yet</option>@foreach ($clients as $c)<option value="{{ $c->id }}" @selected(($client?->id) === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="flex gap-2"><button class="btn-primary flex-1">Create project</button><a href="{{ route('welcome', 6) }}" class="btn-secondary">Skip</a></div>
    </form>
@else
    <div class="text-center"><span class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-50 text-3xl text-emerald-600"><i class="bi bi-check2-circle"></i></span>
        <h1 class="mt-3 text-2xl font-bold text-slate-900">You are ready</h1><p class="mt-1 text-slate-600">Your workspace is set up. Here is what to do next.</p></div>
    <ul class="mt-5 space-y-2 text-sm"><li><a class="flex items-center gap-2 text-brand-700 hover:underline" href="{{ route('clients.index') }}"><i class="bi bi-person-plus"></i> Invite your client to their portal</a></li><li><a class="flex items-center gap-2 text-brand-700 hover:underline" href="{{ route('invoices.create') }}"><i class="bi bi-receipt"></i> Create your first invoice</a></li></ul>
    <form method="POST" action="{{ route('welcome.done') }}" class="mt-6">@csrf<button class="btn-primary w-full">Go to my dashboard</button></form>
@endif
</div>
@endsection
