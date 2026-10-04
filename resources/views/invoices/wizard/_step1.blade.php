@php
    $v = fn ($k, $d = null) => old($k, data_get($invoice, $k, $d));
    $dateVal = fn ($k) => old($k, $invoice->{$k}?->format('Y-m-d'));
    $termsNow = old('terms_days');
    if ($termsNow === null) {
        $termsNow = $invoice->exists ? (string) (array_search($invoice->payment_terms, \App\Models\Invoice::TERMS, true) ?: ($invoice->payment_terms === 'Due on receipt' ? '0' : 'custom')) : (string) (array_search($invoice->payment_terms, \App\Models\Invoice::TERMS, true) ?: 14);
    }
@endphp
<form method="POST" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" class="space-y-6"
      x-data="{ type: '{{ $v('invoice_type', 'domestic') }}', billTo: '{{ $v('bill_to_type', 'company') }}', terms: '{{ $termsNow }}', treatment: '{{ $v('tax_treatment', 'none') }}', cur: '{{ $v('currency') }}', rates: @js($rates ?? []), profiles: @js($taxProfiles ?? []),
          apply(id) { const p = this.profiles.find(x => x.id === id); if (!p) return; this.treatment = p.treatment; if (p.applies_to !== 'all') this.type = p.applies_to; this.$nextTick(() => { const set = (k, v) => { const e = document.getElementById(k); if (e) e.value = v ?? ''; }; set('tax_rate', p.rate || ''); set('tax_label', p.label); set('place_of_supply', p.place_of_supply); set('sac_code', p.sac_code); set('lut_reference', p.lut_reference); }); } }">
    @csrf @if ($invoice->exists) @method('PUT') @endif

    <div class="card space-y-5 p-5">
        <h2 class="font-semibold text-slate-900">Who is this invoice from and to?</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @if (! $isFreelancer && ! $invoice->exists)
                <div>
                    <label class="label" for="freelancer_id">Freelancer (issuer)</label>
                    <select id="freelancer_id" name="freelancer_id" required class="input" onchange="if(this.value) location.href='{{ route('invoices.create') }}?freelancer='+this.value">
                        <option value="">Choose a freelancer</option>
                        @foreach ($freelancers as $m)<option value="{{ $m->user_id }}" @selected($issuer?->id === $m->user_id)>{{ $m->user->name }}</option>@endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">You prepare it for them. They stay the issuer and you cannot approve it yourself.</p>
                </div>
            @endif
            <div>
                <span class="label">Bill to</span>
                <div class="flex gap-2">
                    @unless ($org->mode === 'solo')<label class="chip cursor-pointer" :class="billTo === 'company' && 'chip-active'"><input type="radio" name="bill_to_type" value="company" x-model="billTo" class="sr-only">  {{ $org->name }}</label>@endunless
                    <label class="chip cursor-pointer" :class="billTo === 'client' && 'chip-active'"><input type="radio" name="bill_to_type" value="client" x-model="billTo" class="sr-only"> A client</label>
                </div>
            </div>
            <div x-show="billTo === 'client'" x-cloak>
                <label class="label" for="client_id">Client</label>
                <select id="client_id" name="client_id" class="input" :disabled="billTo !== 'client'">
                    <option value="">Choose a client</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}" @selected($v('client_id') === $c->id)>{{ $c->name }}</option>@endforeach
                </select>
                @if ($clients->isEmpty())<p class="mt-1 text-xs text-slate-500">No clients yet. <a class="text-brand-600 underline" href="{{ route('clients.create') }}">Add one</a>.</p>@endif
            </div>
            <div x-show="billTo === 'client'" x-cloak x-data="{ pid: '{{ $v('project_id') }}' }">
                <label class="label" for="project_id">Project <span class="font-normal text-slate-400">(optional)</span></label>
                <select id="project_id" name="project_id" class="input" x-model="pid" :disabled="billTo !== 'client'">
                    <option value="">Not tied to a project</option>
                    @foreach ($projects as $pr)<option value="{{ $pr->id }}" data-client="{{ $pr->client_id }}">{{ $pr->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="contract_id">Contract <span class="font-normal text-slate-400">(optional)</span></label>
                <select id="contract_id" name="contract_id" class="input">
                    <option value="">No contract</option>
                    @foreach ($contracts as $c)<option value="{{ $c->id }}" @selected($v('contract_id') === $c->id)>{{ $c->reference }} · {{ $c->title ?? '' }}</option>@endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card space-y-5 p-5">
        <h2 class="font-semibold text-slate-900">Dates, currency and type</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <div><label class="label" for="issue_date">Issue date</label><input id="issue_date" type="date" name="issue_date" value="{{ $dateVal('issue_date') }}" required class="input"></div>
            <div>
                <label class="label" for="terms_days">Payment terms</label>
                <select id="terms_days" name="terms_days" x-model="terms" class="input">
                    @foreach (\App\Models\Invoice::TERMS as $d => $l)<option value="{{ $d }}">{{ $l }}</option>@endforeach
                    <option value="custom">Custom due date</option>
                </select>
            </div>
            <div x-show="terms === 'custom'" x-cloak><label class="label" for="due_date">Due date</label><input id="due_date" type="date" name="due_date" value="{{ $dateVal('due_date') }}" class="input" :disabled="terms !== 'custom'"></div>
            <div><label class="label" for="currency">Currency</label>
                <select id="currency" name="currency" x-model="cur" class="input">@foreach ($currencies as $c)<option value="{{ $c }}" @selected($v('currency') === $c)>{{ $c }}</option>@endforeach</select></div>
            <div class="sm:col-span-2">
                <span class="label">Invoice type</span>
                <div class="flex gap-2">
                    <label class="chip cursor-pointer" :class="type === 'domestic' && 'chip-active'"><input type="radio" name="invoice_type" value="domestic" x-model="type" class="sr-only"> Domestic</label>
                    <label class="chip cursor-pointer" :class="type === 'international' && 'chip-active'"><input type="radio" name="invoice_type" value="international" x-model="type" class="sr-only"> International</label>
                </div>
            </div>
            <div><label class="label" for="service_period_start">Service period from</label><input id="service_period_start" type="date" name="service_period_start" value="{{ $dateVal('service_period_start') }}" class="input"></div>
            <div><label class="label" for="service_period_end">to</label><input id="service_period_end" type="date" name="service_period_end" value="{{ $dateVal('service_period_end') }}" class="input"></div>
            <div><label class="label" for="template">Template</label>
                <select id="template" name="template" class="input">@foreach ($templates as $k => $l)<option value="{{ $k }}" @selected($v('template', 'professional') === $k)>{{ $l }}</option>@endforeach</select></div>
        </div>
    </div>

    <div class="card space-y-5 p-5">
        <div>
            <h2 class="font-semibold text-slate-900">Tax</h2>
            <p class="text-sm text-slate-500">Freelancy prints what you choose here. It does not decide which tax applies to you. Check with your accountant if unsure.</p>
        </div>
        @if (! empty($taxProfiles))
        <div><label class="label" for="tax_profile">Apply a saved tax profile</label>
            <select id="tax_profile" class="input sm:max-w-sm" @change="apply($event.target.value)"><option value="">Choose…</option>@foreach ($taxProfiles as $tp)<option value="{{ $tp['id'] }}">{{ $tp['name'] }}</option>@endforeach</select></div>
        @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2"><label class="label" for="tax_treatment">Tax treatment</label>
                <select id="tax_treatment" name="tax_treatment" x-model="treatment" class="input">@foreach (\App\Models\Invoice::TREATMENTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div x-show="!['none','export_lut'].includes(treatment)" x-cloak><label class="label" for="tax_rate">Rate %</label>
                <input id="tax_rate" type="number" step="0.01" min="0" max="100" name="tax_rate" value="{{ old('tax_rate', $taxRate ?: '') }}" class="input"></div>
            <div x-show="treatment === 'custom' || treatment === 'vat'" x-cloak><label class="label" for="tax_label">Tax name</label>
                <input id="tax_label" name="tax_label" value="{{ old('tax_label', $taxLabel) }}" maxlength="30" placeholder="VAT" class="input"></div>
            <div x-show="treatment.startsWith('gst')" x-cloak><label class="label" for="place_of_supply">Place of supply</label>
                <input id="place_of_supply" name="place_of_supply" value="{{ $v('place_of_supply') }}" placeholder="Karnataka" class="input"></div>
            <div x-show="treatment.startsWith('gst') || treatment.startsWith('export')" x-cloak><label class="label" for="sac_code">SAC code</label>
                <input id="sac_code" name="sac_code" value="{{ $v('sac_code') }}" placeholder="998314" class="input"></div>
            <div x-show="treatment === 'export_lut'" x-cloak class="sm:col-span-2"><label class="label" for="lut_reference">LUT reference</label>
                <input id="lut_reference" name="lut_reference" value="{{ $v('lut_reference') }}" class="input"></div>
            <div x-show="type === 'international'" x-cloak><label class="label" for="exchange_rate">Exchange rate to INR <span class="font-normal text-slate-400">(1 unit = ? INR)</span></label>
                <input id="exchange_rate" type="number" step="0.000001" min="0" name="exchange_rate" value="{{ $v('exchange_rate') }}" class="input" :disabled="type !== 'international'">
                <p class="mt-1 text-xs text-slate-500" x-show="rates[cur]" x-cloak>Your saved rate: <button type="button" class="font-semibold text-brand-700 underline" @click="document.getElementById('exchange_rate').value = rates[cur].rate" x-text="rates[cur] ? rates[cur].rate + ' (from ' + rates[cur].on + ')' : ''"></button> · <a class="underline" href="{{ route('settings.exchange-rates') }}">edit rates</a></p></div>
        </div>
    </div>

    <div class="card p-5">
        <label class="label" for="notes">Notes <span class="font-normal text-slate-400">(printed on the invoice)</span></label>
        <textarea id="notes" name="notes" rows="3" maxlength="2000" class="input">{{ $v('notes') }}</textarea>
    </div>

    @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    <div class="flex justify-end"><button class="btn-primary">Save and add services <i class="bi bi-arrow-right"></i></button></div>
</form>
