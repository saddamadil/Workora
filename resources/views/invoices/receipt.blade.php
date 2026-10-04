<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Receipt {{ $invoice->number }}</title>
<style>
    @page { size: A4; margin: 0; }
    body { margin: 0; font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #171717; line-height: 1.5; }
    .bar { height: 8px; background: #ff6b35; }
    .pad { padding: 36px 44px; }
    h1 { font-size: 24px; margin: 0 0 4px; letter-spacing: 1px; }
    .muted { color: #6b6b6b; } .label { font-size: 9px; letter-spacing: 1.2px; text-transform: uppercase; color: #6b6b6b; font-weight: bold; margin-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; } td { vertical-align: top; }
    .box { border: 1px solid #e7e7e4; border-radius: 6px; padding: 14px 16px; }
    .amount { font-size: 26px; font-weight: bold; }
    .paid { display: inline-block; border: 2px solid #047857; color: #047857; font-weight: bold; padding: 2px 10px; border-radius: 4px; letter-spacing: 2px; }
</style></head>
<body><div class="bar"></div><div class="pad">
    <table><tr><td><h1>RECEIPT</h1><div class="muted">For invoice {{ $d['number'] }}</div></td>
        <td style="text-align:right"><div class="paid">PAID</div><div class="muted" style="margin-top:6px">{{ $payment->paid_at?->format('d M Y') }}</div></td></tr></table>
    <table style="margin-top:26px"><tr>
        <td style="width:50%;padding-right:10px"><div class="label">Received by</div><strong>{{ $d['from']['name'] ?? '' }}</strong><div class="muted">{{ is_array($d['from']['address'] ?? null) ? implode(', ', $d['from']['address']) : ($d['from']['address'] ?? '') }}</div></td>
        <td style="width:50%;padding-left:10px"><div class="label">Received from</div><strong>{{ $d['bill_to']['name'] ?? '' }}</strong><div class="muted">{{ is_array($d['bill_to']['address'] ?? null) ? implode(', ', $d['bill_to']['address']) : ($d['bill_to']['address'] ?? '') }}</div></td>
    </tr></table>
    <div class="box" style="margin-top:26px"><div class="label">Amount received</div><div class="amount">{{ money($payment->amount_minor, $payment->currency) }} <span style="font-size:12px" class="muted">{{ $payment->currency }}</span></div></div>
    <table style="margin-top:20px">
        <tr><td class="muted" style="width:35%;padding:4px 0">Payment method</td><td>{{ ucfirst(str_replace('_', ' ', $payment->method)) }}</td></tr>
        <tr><td class="muted" style="padding:4px 0">Reference</td><td>{{ $payment->reference ?: '—' }}</td></tr>
        <tr><td class="muted" style="padding:4px 0">Invoice total</td><td>{{ $d['total'] }}</td></tr>
        <tr><td class="muted" style="padding:4px 0">Balance after this payment</td><td>{{ money($invoice->outstandingMinor(), $invoice->currency) }}</td></tr>
    </table>
    <p class="muted" style="margin-top:34px;font-size:10px">This receipt confirms a payment recorded by {{ $d['from']['name'] ?? 'the supplier' }} against the invoice above.</p>
</div></body></html>
