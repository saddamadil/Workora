@php $cur = $invoice->currency; @endphp
<div class="grid gap-6 lg:grid-cols-3">
<div class="space-y-6 lg:col-span-2">
    <div class="card overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Description</th><th class="px-3 py-3 text-right">Qty</th><th class="px-3 py-3 text-right">Rate</th><th class="px-3 py-3 text-right">Disc.</th><th class="px-5 py-3 text-right">Amount</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoice->items as $item)
                    <tr><td class="px-5 py-2.5 text-slate-800">{{ $item->description }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-slate-600">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit === 'hours' ? 'h' : '' }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-slate-600">{{ money($item->unit_rate_minor, $cur) }}</td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right text-slate-600">{{ (float) $item->discount_percent > 0 ? rtrim(rtrim(number_format((float) $item->discount_percent, 2), '0'), '.').'%' : '' }}</td>
                        <td class="whitespace-nowrap px-5 py-2.5 text-right font-medium text-slate-900">{{ money($item->amount_minor, $cur) }}</td>
                        <td class="pr-4"><form method="POST" action="{{ route('invoices.items.remove', [$invoice, $item]) }}">@csrf @method('DELETE')<button class="text-slate-300 hover:text-red-600" aria-label="Remove line"><i class="bi bi-x-lg"></i></button></form></td></tr>
                @empty<tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">No lines yet. Add one below or import approved work.</td></tr>@endforelse
            </tbody>
            <tfoot class="text-sm">
                <tr class="border-t border-slate-200"><td colspan="4" class="px-5 py-2 text-right text-slate-500">Subtotal</td><td class="px-5 py-2 text-right text-slate-800">{{ money($invoice->subtotal_minor, $cur) }}</td><td></td></tr>
                @foreach ($invoice->taxBreakdown() as $t)<tr><td colspan="4" class="px-5 py-2 text-right text-slate-500">{{ $t['label'] }} ({{ rtrim(rtrim(number_format((float) $t['rate'], 2), '0'), '.') }}%)</td><td class="px-5 py-2 text-right text-slate-800">{{ money($t['amount_minor'], $cur) }}</td><td></td></tr>@endforeach
                <tr><td colspan="4" class="px-5 py-2 text-right font-semibold text-slate-900">Total</td><td class="px-5 py-2 text-right text-lg font-bold text-slate-900">{{ money($invoice->total_minor, $cur) }}</td><td></td></tr>
            </tfoot>
        </table>
    </div>

    <div class="card space-y-4 p-5">
        <h2 class="font-semibold text-slate-900">Add to this invoice</h2>
        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('invoices.import-time', $invoice) }}">@csrf
                <button class="btn-secondary" @disabled(! $availableMinutes)><i class="bi bi-stopwatch"></i> Approved time <span class="text-slate-500">({{ hours($availableMinutes) }})</span></button></form>
            @if ($invoice->contract?->type === 'milestone')
                <form method="POST" action="{{ route('invoices.import-milestones', $invoice) }}">@csrf
                    <button class="btn-secondary" @disabled($availableMilestones->isEmpty())><i class="bi bi-flag"></i> Approved milestones ({{ $availableMilestones->count() }})</button></form>
            @endif
        </div>
        <form method="POST" action="{{ route('invoices.items.add', $invoice) }}" class="grid gap-3 sm:grid-cols-12">@csrf
            <input name="description" required placeholder="Service, e.g. Website design" class="input sm:col-span-5" aria-label="Description">
            <input name="quantity" type="number" step="0.01" min="0.01" value="1" required class="input sm:col-span-2" aria-label="Quantity">
            <select name="unit" class="input sm:col-span-2" aria-label="Unit"><option value="items">items</option><option value="hours">hours</option><option value="fixed">fixed</option></select>
            <input name="unit_rate" type="number" step="0.01" min="0" required placeholder="Rate" class="input sm:col-span-1" aria-label="Rate">
            <input name="discount_percent" type="number" step="0.01" min="0" max="100" placeholder="Disc. %" class="input sm:col-span-1" aria-label="Discount percent">
            <button class="btn-secondary sm:col-span-1" aria-label="Add line"><i class="bi bi-plus-lg"></i></button>
        </form>
        @if ($errors->any())<div class="text-sm text-red-700">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    </div>
</div>

<div class="space-y-6">
    <form method="POST" action="{{ route('invoices.payment-profile', $invoice) }}" class="card space-y-3 p-5">@csrf
        <h2 class="font-semibold text-slate-900">How to pay</h2>
        @forelse ($profiles as $p)
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-3 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                <input type="radio" name="payout_method_id" value="{{ $p->id }}" @checked($invoice->payout_method_id === $p->id) class="mt-1">
                <span><span class="block font-medium text-slate-900">{{ $p->label ?? ucfirst($p->kind) }} <span class="text-xs font-normal text-slate-500">{{ $p->isInternational() ? 'International' : 'India' }}</span></span>
                    <span class="block text-slate-500">{{ $p->summary() }}</span></span>
            </label>
        @empty
            <p class="text-sm text-slate-500">No payment profile yet. <a class="text-brand-600 underline" href="{{ $isFreelancer ? route('team.payment-profiles') : route('team.payment-profiles') }}">Add one</a>.</p>
        @endforelse
        @if ($profiles->isNotEmpty())<button class="btn-secondary w-full">Use this profile</button>@endif
    </form>
    <div class="flex gap-2">
        <a href="{{ route('invoices.edit', [$invoice, 'step' => 1]) }}" class="btn-secondary flex-1"><i class="bi bi-arrow-left"></i> Details</a>
        <a href="{{ route('invoices.edit', [$invoice, 'step' => 3]) }}" class="btn-primary flex-1">Preview <i class="bi bi-arrow-right"></i></a>
    </div>
</div>
</div>
