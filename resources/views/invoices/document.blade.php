@php
    /** @var array $d  built by App\Services\InvoiceDocument */
    $pdf = $pdf ?? false;
    $themes = [
        'professional' => ['accent' => '#1e3a5f', 'soft' => '#f1f5f9', 'headBg' => '#1e3a5f', 'headFg' => '#ffffff', 'rule' => '#cbd5e1', 'bar' => 6],
        'modern' => ['accent' => '#4747d8', 'soft' => '#eef2ff', 'headBg' => '#4747d8', 'headFg' => '#ffffff', 'rule' => '#e0e7ff', 'bar' => 0],
        'minimal' => ['accent' => '#111827', 'soft' => '#ffffff', 'headBg' => '#ffffff', 'headFg' => '#111827', 'rule' => '#d1d5db', 'bar' => 0],
        'international' => ['accent' => '#0f766e', 'soft' => '#f0fdfa', 'headBg' => '#0f766e', 'headFg' => '#ffffff', 'rule' => '#99f6e4', 'bar' => 6],
        'gst' => ['accent' => '#9a3412', 'soft' => '#fff7ed', 'headBg' => '#9a3412', 'headFg' => '#ffffff', 'rule' => '#fed7aa', 'bar' => 6],
    ];
    $t = $themes[$d['template']] ?? $themes['professional'];
    $isMinimal = $d['template'] === 'minimal';
    $statusLabel = ['paid' => 'PAID', 'overdue' => 'OVERDUE', 'draft' => 'DRAFT', 'rejected' => 'DRAFT'][$d['status']] ?? null;
    $meta = array_filter([
        'Service period' => $d['period'],
        'Payment terms' => $d['payment_terms'],
        'Currency' => $d['currency'],
        'Place of supply' => $d['domestic']['place_of_supply'] ?? null,
        'SAC' => $d['domestic']['sac'] ?? ($d['international']['sac'] ?? null),
        'Exchange rate' => isset($d['international']['exchange_rate']) ? '1 '.$d['currency'].' = ₹'.$d['international']['exchange_rate'] : null,
        'LUT' => $d['international']['lut'] ?? null,
    ]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $d['title'] }} {{ $d['number'] }}</title>
<style>
    @page { size: A4; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'DejaVu Sans', 'Segoe UI', Arial, sans-serif; font-size: 11px; line-height: 1.45; color: #1f2937; background: {{ $pdf ? '#ffffff' : '#e5e7eb' }}; }
    .sheet { {{ $pdf ? 'width: auto' : 'max-width: 794px' }}; margin: {{ $pdf ? '0' : '24px auto' }}; background: #ffffff; {{ $pdf ? '' : 'box-shadow: 0 1px 8px rgba(0,0,0,.15);' }} }
    .bar { height: {{ $t['bar'] }}px; background: {{ $t['accent'] }}; }
    .pad { padding: {{ $pdf ? '24px 38px 8px 38px' : '34px 40px 30px 40px' }}; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .muted { color: #6b7280; }
    .small { font-size: 9.5px; }
    .label { font-size: 9px; letter-spacing: 1.2px; text-transform: uppercase; color: {{ $t['accent'] }}; font-weight: bold; margin-bottom: 4px; }
    .title { font-size: 26px; font-weight: bold; letter-spacing: 1px; color: {{ $t['accent'] }}; margin: 0; }
    .num { font-size: 13px; font-weight: bold; }
    .name { font-size: 14px; font-weight: bold; color: #111827; }
    .img { width: 54px; height: 54px; border-radius: 6px; margin-right: 10px; }
    .logo { max-height: 54px; max-width: 150px; }
    .party { background: {{ $t['soft'] }}; padding: 12px 14px; border-radius: 6px; {{ $isMinimal ? 'border: 1px solid '.$t['rule'].';' : '' }} }
    .meta td { padding: 6px 10px; border-top: 1px solid {{ $t['rule'] }}; border-bottom: 1px solid {{ $t['rule'] }}; }
    .meta .k { font-size: 8.5px; text-transform: uppercase; letter-spacing: .8px; color: #6b7280; }
    .items th { background: {{ $t['headBg'] }}; color: {{ $t['headFg'] }}; text-align: left; padding: 8px 10px; font-size: 9.5px; letter-spacing: .6px; text-transform: uppercase; {{ $isMinimal ? 'border-top: 2px solid #111827; border-bottom: 1px solid #111827;' : '' }} }
    .items td { padding: 7px 10px; border-bottom: 1px solid {{ $t['rule'] }}; }
    .r { text-align: right; }
    .totals td { padding: 5px 10px; }
    .grand td { font-size: 14px; font-weight: bold; color: {{ $t['accent'] }}; border-top: 2px solid {{ $t['accent'] }}; padding-top: 8px; }
    .box { border: 1px solid {{ $t['rule'] }}; border-radius: 6px; padding: 12px 14px; }
    .stamp { display: inline-block; border: 2px solid {{ $statusLabel === 'PAID' ? '#047857' : '#b91c1c' }}; color: {{ $statusLabel === 'PAID' ? '#047857' : '#b91c1c' }}; font-weight: bold; padding: 2px 10px; border-radius: 4px; letter-spacing: 2px; font-size: 11px; margin-top: 6px; }
    .keep { page-break-inside: avoid; }
    @media print { body { background: #ffffff; } .sheet { margin: 0; box-shadow: none; width: auto; } }
</style>
</head>
<body>
<div class="sheet">
    <div class="bar"></div>
    <div class="pad">

        {{-- Header --}}
        <table><tr>
            <td style="width: 58%">
                <table><tr>
                    @if ($d['header_image'])<td style="width: {{ ($d['header']['kind'] ?? '') === 'company' ? '160' : '66' }}px">
                        <img src="{{ $d['header_image'] }}" alt="" class="{{ ($d['header']['kind'] ?? '') === 'company' ? 'logo' : 'img' }}"></td>@endif
                    <td><div class="name">{{ $d['header']['name'] }}</div>
                        @if (! empty($d['header']['title']))<div class="muted">{{ $d['header']['title'] }}</div>@endif
                        @foreach ($d['header']['address'] as $line)<div class="muted small">{{ $line }}</div>@endforeach
                        @if (! empty($d['header']['country']))<div class="muted small">{{ $d['header']['country'] }}</div>@endif</td>
                </tr></table>
            </td>
            <td class="r">
                <p class="title">{{ $d['title'] }}</p>
                <div class="num">{{ $d['number'] }}</div>
                <div class="muted">Issued {{ $d['issue_date'] }}</div>
                <div class="muted">Due {{ $d['due_date'] }}</div>
                @if ($statusLabel)<div class="stamp">{{ $statusLabel }}</div>@endif
            </td>
        </tr></table>

        <div style="height: 20px"></div>

        {{-- Parties --}}
        <table><tr>
            <td style="width: 49%"><div class="party">
                <div class="label">From</div>
                <table><tr>
                    @if ($d['from_image'])<td style="width: 64px"><img src="{{ $d['from_image'] }}" alt="" class="img"></td>@endif
                    <td><div class="name" style="font-size: 12.5px">{{ $d['from']['name'] }}</div>
                        @if (! empty($d['from']['title']))<div class="muted">{{ $d['from']['title'] }}</div>@endif</td>
                </tr></table>
                <div style="margin-top: 4px">
                    @foreach ($d['from']['address'] as $line)<div>{{ $line }}</div>@endforeach
                    @if ($d['from']['country'])<div>{{ $d['from']['country'] }}</div>@endif
                    @if ($d['from']['email'])<div class="muted">{{ $d['from']['email'] }}</div>@endif
                    @if ($d['from']['phone'])<div class="muted">{{ $d['from']['phone'] }}</div>@endif
                    @foreach ($d['from']['tax'] as [$k, $v])<div class="small"><span class="muted">{{ $k }}:</span> {{ $v }}</div>@endforeach
                    @if (! empty($d['from']['code']))<div class="small"><span class="muted">Freelancer ID:</span> {{ $d['from']['code'] }}</div>@endif
                </div>
            </div></td>
            <td style="width: 2%"></td>
            <td style="width: 49%"><div class="party">
                <div class="label">Bill to</div>
                <table><tr>
                    @if ($d['bill_image'])<td style="width: 110px"><img src="{{ $d['bill_image'] }}" alt="" class="logo" style="max-height: 46px; max-width: 100px"></td>@endif
                    <td><div class="name" style="font-size: 12.5px">{{ $d['bill_to']['name'] }}</div>
                        @if (! empty($d['bill_to']['title']))<div class="muted">{{ $d['bill_to']['title'] }}</div>@endif</td>
                </tr></table>
                <div style="margin-top: 4px">
                    @foreach ($d['bill_to']['address'] as $line)<div>{{ $line }}</div>@endforeach
                    @if ($d['bill_to']['country'])<div>{{ $d['bill_to']['country'] }}</div>@endif
                    @if ($d['bill_to']['email'])<div class="muted">{{ $d['bill_to']['email'] }}</div>@endif
                    @if ($d['bill_to']['phone'])<div class="muted">{{ $d['bill_to']['phone'] }}</div>@endif
                    @foreach ($d['bill_to']['tax'] as [$k, $v])<div class="small"><span class="muted">{{ $k }}:</span> {{ $v }}</div>@endforeach
                </div>
            </div></td>
        </tr></table>

        @if ($meta)
            <div style="height: 14px"></div>
            <table class="meta"><tr>
                @foreach ($meta as $k => $v)<td><div class="k">{{ $k }}</div><div>{{ $v }}</div></td>@endforeach
            </tr></table>
        @endif

        <div style="height: 18px"></div>

        {{-- Services --}}
        <table class="items">
            <thead><tr><th>Description</th><th class="r" style="width: 60px">Qty</th><th class="r" style="width: 95px">Rate</th><th class="r" style="width: 105px">Amount</th></tr></thead>
            <tbody>
                @forelse ($d['items'] as $item)
                    <tr><td>{{ $item['description'] }}</td>
                        <td class="r">{{ $item['quantity'] }}@if ($item['unit'] === 'hours') h @endif</td>
                        <td class="r">{{ $item['rate'] }}</td><td class="r"><strong>{{ $item['amount'] }}</strong></td></tr>
                @empty
                    <tr><td colspan="4" class="muted" style="text-align:center; padding: 18px">No lines yet</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Totals --}}
        <table class="keep" style="margin-top: 8px"><tr>
            <td style="width: 52%; padding-right: 16px">
                @if ($d['tax_treatment'] && $d['tax_treatment'] !== 'No tax charged')<div class="small muted" style="margin-top: 8px">Tax treatment: {{ $d['tax_treatment'] }}</div>@endif
                @if ($d['zero_rated_note'])<div class="small muted" style="margin-top: 4px">Export of services, supplied without payment of tax under a Letter of Undertaking.@if (! empty($d['international']['lut'])) Ref: {{ $d['international']['lut'] }}@endif</div>@endif
            </td>
            <td>
                <table class="totals">
                    <tr><td class="muted">Subtotal</td><td class="r">{{ $d['subtotal'] }}</td></tr>
                    @foreach ($d['tax_lines'] as $line)<tr><td class="muted">{{ $line['label'] }}</td><td class="r">{{ $line['amount'] }}</td></tr>@endforeach
                    <tr class="grand"><td>Total ({{ $d['currency'] }})</td><td class="r">{{ $d['total'] }}</td></tr>
                    @if (! empty($d['international']['inr_equivalent']))<tr><td class="muted small">INR equivalent</td><td class="r small">{{ $d['international']['inr_equivalent'] }}</td></tr>@endif
                    @if ($d['paid'])<tr><td class="muted">Paid</td><td class="r">{{ $d['paid'] }}</td></tr><tr><td><strong>Balance due</strong></td><td class="r"><strong>{{ $d['balance'] }}</strong></td></tr>@endif
                </table>
            </td>
        </tr></table>

        <div style="height: 16px"></div>

        {{-- Payment --}}
        @if ($d['payment'])
            <div class="box keep">
                <table><tr>
                    <td>
                        <div class="label">Payment details</div>
                        <table>
                            @foreach ($d['payment']['lines'] as [$k, $v])<tr><td class="muted small" style="width: 105px; padding: 1px 0">{{ $k }}</td><td style="padding: 1px 0">{{ $v }}</td></tr>@endforeach
                        </table>
                    </td>
                    @if ($d['qr'])<td class="r" style="width: 120px"><img src="{{ $d['qr'] }}" alt="" style="width: 100px; height: 100px"><div class="small muted">{{ $d['payment']['upi'] && $d['currency'] === 'INR' ? 'Scan to pay with UPI' : 'Scan to open the payment page' }}</div></td>@endif
                </tr></table>
            </div>
        @endif

        @if ($d['notes'])
            <div class="keep" style="margin-top: 14px"><div class="label">Notes</div><div style="white-space: pre-line">{{ $d['notes'] }}</div></div>
        @endif

        <table class="keep" style="margin-top: 14px"><tr>
            <td class="muted small" style="vertical-align: bottom">@if ($d['payment_terms'])Payment terms: {{ $d['payment_terms'] }}. @endif Thank you.</td>
            <td class="r" style="width: 210px">
                @if ($d['signature'])<img src="{{ $d['signature'] }}" alt="" style="max-height: 56px; max-width: 190px">@else<div style="height: 40px"></div>@endif
                <div style="border-top: 1px solid #9ca3af; margin-top: 2px; padding-top: 3px" class="small muted">{{ $d['signature_name'] }}</div>
            </td>
        </tr></table>
    </div>
</div>
@if (! empty($autoPrint))<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>@endif
</body>
</html>
