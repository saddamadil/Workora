<div class="grid gap-6 lg:grid-cols-3">
<div class="lg:col-span-2">
    <div class="card overflow-hidden">
        <iframe title="Invoice preview" src="{{ route('invoices.preview', $invoice) }}" class="h-[1100px] w-full bg-white"></iframe>
    </div>
</div>
<div class="space-y-6">
    @if (! empty($warnings))
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <h2 class="mb-2 font-semibold"><i class="bi bi-exclamation-triangle"></i> Review tax settings and details</h2>
            <ul class="list-disc space-y-1 pl-5">@foreach ($warnings as $w)<li>{{ $w }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('invoices.template', $invoice) }}" class="card flex items-end gap-2 p-5">@csrf
        <div class="flex-1"><label class="label" for="tpl">Template</label>
            <select id="tpl" name="template" class="input" onchange="this.form.submit()">@foreach ($templates as $k => $l)<option value="{{ $k }}" @selected($invoice->template === $k)>{{ $l }}</option>@endforeach</select></div>
        <noscript><button class="btn-secondary">Apply</button></noscript>
    </form>

    <div class="card space-y-2 p-5">
        <a href="{{ route('invoices.edit', [$invoice, 'step' => 2]) }}" class="btn-secondary w-full"><i class="bi bi-pencil"></i> Edit</a>
        <a href="{{ route('invoices.pdf', $invoice) }}" class="btn-secondary w-full"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
        <a href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener" class="btn-secondary w-full"><i class="bi bi-printer"></i> Print</a>
        <form method="POST" action="{{ route('invoices.duplicate', $invoice) }}">@csrf<button class="btn-secondary w-full"><i class="bi bi-files"></i> Duplicate</button></form>
        <a href="{{ route('invoices.index', ['status' => 'draft']) }}" class="btn-secondary w-full"><i class="bi bi-save"></i> Save draft and close</a>
    </div>

    @can('submit', $invoice)
        <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="card space-y-3 p-5">@csrf
            <h2 class="font-semibold text-slate-900">Send invoice</h2>
            <p class="text-sm text-slate-500">Sending locks the details and puts the invoice in the approval queue.</p>
            <div><label class="label" for="email_to">Also email a PDF copy <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="email_to" type="email" name="email_to" class="input" placeholder="accounts@client.com"></div>
            <button class="btn-primary w-full" @disabled($invoice->total_minor <= 0)><i class="bi bi-send"></i> Send invoice</button>
        </form>
    @endcan
</div>
</div>
