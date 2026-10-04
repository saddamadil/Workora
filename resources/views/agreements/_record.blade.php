{{-- Signature record, shown to both sides. --}}
<div class="card h-fit space-y-2 p-5 text-sm">
    <h2 class="font-semibold text-slate-900">Signature record</h2>
    @if ($agreement->status === 'signed')
        <p>Signed by <strong>{{ $agreement->signed_name }}</strong></p>
        <p class="text-slate-600">{{ $agreement->signed_at->format('d M Y, H:i') }} UTC offset {{ $agreement->signed_at->format('P') }}</p>
        <p class="text-slate-600">From IP address {{ $agreement->signed_ip }}</p>
        <p class="break-all text-xs text-slate-500">Text fingerprint (SHA-256): {{ $agreement->body_hash }}</p>
        @unless ($agreement->intact())<p class="rounded bg-red-50 p-2 text-red-700">The text no longer matches what was signed.</p>@endunless
    @elseif ($agreement->status === 'declined')<p>Declined.@if ($agreement->decline_reason) Reason: {{ $agreement->decline_reason }}@endif</p>
    @elseif ($agreement->status === 'sent')<p>Waiting for the client's signature. Sent {{ $agreement->sent_at?->diffForHumans() }}.</p>
    @elseif ($agreement->status === 'void')<p>Voided.</p>@else<p>Draft. The client cannot see it yet.</p>@endif
    <p class="pt-1 text-xs text-slate-500">A typed name with a time and address record is a simple electronic signature. Whether it is enough depends on the document and the country; ask a professional for important contracts.</p>
</div>
