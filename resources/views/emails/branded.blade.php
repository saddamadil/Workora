<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $heading }}</title></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 12px;"><tr><td align="center">
    <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
        <tr><td style="background:#c2410c;padding:18px 28px;color:#ffffff;font-size:18px;font-weight:700;letter-spacing:.2px;">{{ config('app.name', 'Freelancy') }}</td></tr>
        <tr><td style="padding:28px;">
            <h1 style="margin:0 0 14px;font-size:20px;line-height:1.3;color:#0f172a;">{{ $heading }}</h1>
            @foreach (preg_split('/\n{2,}/', trim($body)) as $para)
                <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#334155;">{!! nl2br(e($para)) !!}</p>
            @endforeach
            @if (! empty($facts))
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:6px 0 18px;width:100%;border:1px solid #e2e8f0;border-radius:8px;">
                    @foreach ($facts as $k => $v)<tr><td style="padding:8px 12px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;">{{ $k }}</td><td style="padding:8px 12px;font-size:14px;font-weight:600;color:#0f172a;text-align:right;border-bottom:1px solid #f1f5f9;">{{ $v }}</td></tr>@endforeach
                </table>
            @endif
            @if (! empty($url))
                <p style="margin:22px 0;"><a href="{{ $url }}" style="display:inline-block;background:#c2410c;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:8px;">{{ $button ?? 'Open' }}</a></p>
                <p style="margin:0;font-size:12px;color:#94a3b8;word-break:break-all;">If the button does not work, copy this link: {{ $url }}</p>
            @endif
        </td></tr>
        <tr><td style="padding:16px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;">{{ $footer ?? 'Sent by '.config('app.name', 'Freelancy').'.' }}</td></tr>
    </table>
</td></tr></table>
</body></html>
