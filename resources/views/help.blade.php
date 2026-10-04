@extends('layouts.app')
@section('title', 'Help')
@section('content')
@php $client = $role?->isClient(); @endphp
<x-page-title title="Help and support" sub="Short answers to common questions." />
<div class="grid max-w-3xl gap-3">
    @foreach ($client ? [
        ['How do I see what is happening on my project?', 'Open Projects and pick one. The Overview shows progress and anything waiting for your review. Tasks, Milestones, Files and Activity have their own tabs.'],
        ['How do I approve work?', 'When your freelancer sends something for review, it appears on the project Overview with Approve and Request changes buttons. Requesting changes needs a short comment so it is clear what to fix.'],
        ['How do I ask for something new?', 'Use Requests, then Request work. Your freelancer can accept, decline or discuss it, and you will be notified at each step.'],
        ['How do I pay an invoice?', 'Open the invoice for the bank or UPI details, or the Pay online button when your freelancer has added a payment link. After paying, press "I have paid this invoice" and add the transaction reference so it can be matched.'],
        ['Who can see my information?', 'Only you and your freelancer. Other clients never see your projects, files, messages or invoices.'],
    ] : [
        ['How do I invite a client?', 'Open the client, then press Invite client. They get a link to create their portal login, or to connect an account they already have.'],
        ['What does the client see?', 'Only their own projects, non-private tasks, files you share, milestones, sent invoices and messages. Private tasks, private notes and your revenue are never shown.'],
        ['How do invoices get paid?', 'Add a payment profile (bank, UPI, IBAN or a payment link). Clients pay outside Freelancy and tell you with a reference. You confirm it and the payment is recorded. Freelancy records payments; it does not move money.'],
        ['How do reminders work?', 'A daily job sends due-soon and overdue reminders. On Hostinger, add a Cron Job running every minute: php /home/YOUR_USER/domains/YOUR_DOMAIN/workora/artisan schedule:run'],
        ['Is the tax on invoices checked?', 'No. Freelancy prints the tax settings you choose and warns about missing details. It does not decide which tax applies to you. Ask your accountant.'],
        ['What can a client sign or accept?', 'A quote (accepting it creates the project) and an agreement (signed by typing their name; the time, address and a text fingerprint are recorded). Only the account owner can accept or sign; colleagues can read. A typed signature is a simple electronic signature, so ask a professional about important contracts.'],
        ['How do recurring invoices work?', 'Open an invoice and choose Make recurring. A new draft is made on each date (or sent automatically if you choose that). It needs the daily job described above. Time and milestone lines are not repeated.'],
        ['Keyboard shortcuts', 'Press N to create something new, and Ctrl or Cmd + K to search.'],
    ] as [$q, $a])
        <details class="card group p-5"><summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 font-semibold text-slate-900">{{ $q }}<i class="bi bi-chevron-down text-slate-400 transition group-open:rotate-180"></i></summary><p class="mt-2 text-sm text-slate-700">{{ $a }}</p></details>
    @endforeach
    <p class="mt-2 text-sm text-slate-500">Still stuck? {{ $client ? 'Send your freelancer a message.' : 'Contact the person who set up this site.' }} @if ($client)<a class="text-brand-600 underline" href="{{ route('portal.messages.index') }}">Open messages</a>@endif</p>
</div>
@endsection
