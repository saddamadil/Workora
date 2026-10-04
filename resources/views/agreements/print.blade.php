<!doctype html><html><head><meta charset="utf-8"><title>{{ $agreement->title }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#1e293b;line-height:1.6}h1{font-size:20px;margin:0 0 4px}.muted{color:#64748b}.box{border:1px solid #cbd5e1;padding:12px;margin-top:28px}.body{white-space:pre-wrap;margin-top:18px}</style></head>
<body><h1>{{ $agreement->title }}</h1><div class="muted">{{ $agreement->client?->name }} · prepared by {{ $agreement->author?->name }}</div>
<div class="body">{{ $agreement->body }}</div>
<div class="box"><strong>Signature record</strong><br>
@if ($agreement->status === 'signed')Signed by {{ $agreement->signed_name }} on {{ $agreement->signed_at->format('d M Y, H:i') }} (UTC{{ $agreement->signed_at->format('P') }})<br>IP address: {{ $agreement->signed_ip }}<br>Text fingerprint (SHA-256): {{ $agreement->body_hash }}
@else Not signed. Status: {{ $agreement->status }}.@endif</div></body></html>
