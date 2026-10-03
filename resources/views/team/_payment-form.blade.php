{{-- One payment profile form. $profile is an existing PayoutMethod or null; $kind is fixed when editing. --}}
@php
    $isNew = ! $profile;
    $d = fn ($k) => old($k, $profile?->detail($k));
    $uid = $profile?->id ?? 'new';
@endphp
<form method="POST" action="{{ $isNew ? route('team.payment-profiles.store') : route('team.payment-profiles.update', $profile) }}" class="space-y-4"
      x-data="{ kind: '{{ old('kind', $profile?->kind ?? 'domestic') }}' }">
    @csrf @unless ($isNew) @method('PUT') @endunless
    @if ($isNew)
        <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Kind of profile">
            <label :class="kind === 'domestic' ? 'border-brand-600 bg-brand-50' : 'border-slate-200'" class="cursor-pointer rounded-xl border p-3 text-center text-sm font-medium"><input type="radio" name="kind" value="domestic" x-model="kind" class="sr-only"><i class="bi bi-bank me-1"></i> India</label>
            <label :class="kind === 'international' ? 'border-brand-600 bg-brand-50' : 'border-slate-200'" class="cursor-pointer rounded-xl border p-3 text-center text-sm font-medium"><input type="radio" name="kind" value="international" x-model="kind" class="sr-only"><i class="bi bi-globe2 me-1"></i> International</label>
        </div>
    @else <input type="hidden" name="kind" value="{{ $profile->kind }}"> @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2"><label class="label" for="label-{{ $uid }}">Profile name</label><input id="label-{{ $uid }}" name="label" value="{{ old('label', $profile?->label) }}" required class="input" placeholder="e.g. HDFC savings, Wise USD"></div>
        <div><label class="label" for="cur-{{ $uid }}">Currency</label>
            <select id="cur-{{ $uid }}" name="currency" class="input">@foreach ($currencies as $c)<option @selected(old('currency', $profile?->currency ?? 'INR') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div class="sm:col-span-3"><label class="label" for="holder-{{ $uid }}">Account holder name</label><input id="holder-{{ $uid }}" name="account_holder" value="{{ $d('account_holder') }}" required class="input"></div>
    </div>

    <div x-show="kind === 'domestic'" class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="bank-{{ $uid }}">Bank name</label><input id="bank-{{ $uid }}" name="bank_name" value="{{ $d('bank_name') }}" class="input" :disabled="kind !== 'domestic'"></div>
        <div><label class="label" for="branch-{{ $uid }}">Branch</label><input id="branch-{{ $uid }}" name="branch" value="{{ $d('branch') }}" class="input" :disabled="kind !== 'domestic'"></div>
        <div><label class="label" for="acct-{{ $uid }}">Account number</label><input id="acct-{{ $uid }}" name="account_number" value="{{ $d('account_number') }}" class="input font-mono" inputmode="numeric" autocomplete="off" :disabled="kind !== 'domestic'"></div>
        <div><label class="label" for="ifsc-{{ $uid }}">IFSC</label><input id="ifsc-{{ $uid }}" name="ifsc" value="{{ $d('ifsc') }}" class="input font-mono uppercase" maxlength="11" placeholder="HDFC0001234" :disabled="kind !== 'domestic'"></div>
        <div class="sm:col-span-2"><label class="label" for="upi-{{ $uid }}">UPI ID <span class="font-normal text-slate-400">(adds a payment QR code to invoices)</span></label><input id="upi-{{ $uid }}" name="upi_id" value="{{ $d('upi_id') }}" class="input" placeholder="name@okbank" :disabled="kind !== 'domestic'"></div>
        <p class="text-xs text-slate-500 sm:col-span-2">Your PAN and GSTIN are taken from your profile, so they are never typed twice.</p>
    </div>

    <div x-show="kind === 'international'" x-cloak class="grid gap-4 sm:grid-cols-2">
        <div><label class="label" for="ibank-{{ $uid }}">Bank name</label><input id="ibank-{{ $uid }}" name="bank_name" value="{{ $d('bank_name') }}" class="input" :disabled="kind !== 'international'"></div>
        <div><label class="label" for="icountry-{{ $uid }}">Bank country</label>
            <select id="icountry-{{ $uid }}" name="country_code" class="input" :disabled="kind !== 'international'"><option value="">Choose…</option>@foreach ($countries as $code => $n)<option value="{{ $code }}" @selected(old('country_code', $profile?->country_code) === $code)>{{ $n }}</option>@endforeach</select></div>
        <div class="sm:col-span-2"><label class="label" for="baddr-{{ $uid }}">Bank address</label><input id="baddr-{{ $uid }}" name="bank_address" value="{{ $d('bank_address') }}" class="input" :disabled="kind !== 'international'"></div>
        <div><label class="label" for="iban-{{ $uid }}">IBAN</label><input id="iban-{{ $uid }}" name="iban" value="{{ $d('iban') }}" class="input font-mono uppercase" :disabled="kind !== 'international'"></div>
        <div><label class="label" for="swift-{{ $uid }}">SWIFT / BIC</label><input id="swift-{{ $uid }}" name="swift" value="{{ $d('swift') }}" class="input font-mono uppercase" maxlength="11" :disabled="kind !== 'international'"></div>
        <div><label class="label" for="iacct-{{ $uid }}">Account number <span class="font-normal text-slate-400">(if no IBAN)</span></label><input id="iacct-{{ $uid }}" name="account_number" value="{{ $d('account_number') }}" class="input font-mono" autocomplete="off" :disabled="kind !== 'international'"></div>
        <div><label class="label" for="route-{{ $uid }}">Routing number</label><input id="route-{{ $uid }}" name="routing_number" value="{{ $d('routing_number') }}" class="input font-mono" :disabled="kind !== 'international'"></div>
        <div><label class="label" for="sort-{{ $uid }}">Sort code (UK)</label><input id="sort-{{ $uid }}" name="sort_code" value="{{ $d('sort_code') }}" class="input font-mono" placeholder="12-34-56" :disabled="kind !== 'international'"></div>
        <div><label class="label" for="aba-{{ $uid }}">ABA (US)</label><input id="aba-{{ $uid }}" name="aba" value="{{ $d('aba') }}" class="input font-mono" maxlength="9" :disabled="kind !== 'international'"></div>
        <div class="sm:col-span-2"><label class="label" for="inter-{{ $uid }}">Intermediary bank <span class="font-normal text-slate-400">(if your bank needs one)</span></label><input id="inter-{{ $uid }}" name="intermediary_bank" value="{{ $d('intermediary_bank') }}" class="input" :disabled="kind !== 'international'"></div>
        <div><label class="label" for="prov-{{ $uid }}">Payment provider</label><input id="prov-{{ $uid }}" name="payment_provider" value="{{ $d('payment_provider') }}" class="input" placeholder="Wise, PayPal, Payoneer…" :disabled="kind !== 'international'"></div>
        <div><label class="label" for="plink-{{ $uid }}">Payment link <span class="font-normal text-slate-400">(adds a QR code)</span></label><input id="plink-{{ $uid }}" name="payment_link" type="url" value="{{ $d('payment_link') }}" class="input" placeholder="https://" :disabled="kind !== 'international'"></div>
    </div>
    <div><label class="label" for="notes-{{ $uid }}">Instructions for the payer <span class="font-normal text-slate-400">(optional)</span></label><input id="notes-{{ $uid }}" name="notes" value="{{ $d('notes') }}" class="input" maxlength="500"></div>
    <div class="flex justify-end"><button class="btn-primary">{{ $isNew ? 'Save profile' : 'Update profile' }}</button></div>
</form>
