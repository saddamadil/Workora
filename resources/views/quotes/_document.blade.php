{{-- The quote as the client reads it. Used by the staff page and the portal. --}}
<div class="card divide-y divide-slate-100">
    <div class="space-y-2 p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $quote->number }}</p><h2 class="text-xl font-bold text-slate-900">{{ $quote->title }}</h2>
        @if ($quote->intro)<p class="whitespace-pre-line text-sm text-slate-700">{{ $quote->intro }}</p>@endif</div>
    <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-2 text-start">Item</th><th class="px-3 py-2 text-end">Qty</th><th class="px-3 py-2 text-end">Rate</th><th class="px-5 py-2 text-end">Amount</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@foreach ($quote->items as $i)<tr><td class="px-5 py-2.5">{{ $i['description'] }}</td><td class="px-3 py-2.5 text-end">{{ rtrim(rtrim(number_format($i['quantity'], 2), '0'), '.') }}</td><td class="px-3 py-2.5 text-end">{{ money($i['unit_rate_minor'], $quote->currency) }}</td><td class="px-5 py-2.5 text-end">{{ money((int) round($i['quantity'] * $i['unit_rate_minor']), $quote->currency) }}</td></tr>@endforeach</tbody></table></div>
    <div class="space-y-1 p-5 text-sm"><div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span>{{ money($quote->subtotalMinor(), $quote->currency) }}</span></div>
        @if ($quote->tax_rate > 0)<div class="flex justify-between"><span class="text-slate-500">{{ $quote->tax_label ?: 'Tax' }} ({{ rtrim(rtrim(number_format($quote->tax_rate, 2), '0'), '.') }}%)</span><span>{{ money($quote->taxMinor(), $quote->currency) }}</span></div>@endif
        <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold text-slate-900"><span>Total</span><span>{{ money($quote->totalMinor(), $quote->currency) }}</span></div></div>
    @if ($quote->terms)<div class="p-5"><p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Terms</p><p class="whitespace-pre-line text-sm text-slate-700">{{ $quote->terms }}</p></div>@endif
</div>
