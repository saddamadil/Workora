@extends('layouts.guest')
@section('title', 'Workora · Run your freelancers and projects in one place')
@section('width', 'max-w-4xl')
@section('content')
<div class="text-center">
    <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">Work with freelancers.<br class="hidden sm:block"> Without the chaos.</h1>
    <p class="mx-auto mt-4 max-w-xl text-slate-600">Projects, tasks, tracked time, contracts, invoices and payments for companies and the freelancers they hire, with file sharing built in.</p>
    <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
        <a href="{{ route('register', ['as' => 'company']) }}" class="btn-primary px-6 py-3"><i class="bi bi-building"></i> I run a company</a>
        <a href="{{ route('register', ['as' => 'freelancer']) }}" class="btn-secondary px-6 py-3"><i class="bi bi-person-workspace"></i> I'm a freelancer</a>
    </div>
    <p class="mt-3 text-sm text-slate-500">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:underline">Sign in</a></p>

    <div class="mt-12 grid gap-3 text-left sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['bi-kanban', 'Projects and tasks', 'Assign work, follow it on a board, review what is handed in and send it back with a checklist of changes.'],
            ['bi-stopwatch', 'Time that adds up', 'A timer or manual entries, weekly timesheets, and approval by the company before anything is billed.'],
            ['bi-file-earmark-ruled', 'Clear contracts', 'Hourly, fixed price, milestones or a monthly retainer. The freelancer accepts online.'],
            ['bi-receipt', 'Invoices from approved work', 'One click turns approved hours and milestones into an invoice. Hours are never billed twice.'],
            ['bi-cash-coin', 'Payments you can trace', 'Record what was paid, how, and what is still owed. Earnings and payables at a glance.'],
            ['bi-link-45deg', 'Share files safely', 'Upload documents and images, then share a link with a password, an expiry or a download limit.'],
        ] as [$icon, $title, $text])
            <div class="card p-5">
                <i class="bi {{ $icon }} text-2xl text-brand-600"></i>
                <div class="mt-2 font-semibold text-slate-900">{{ $title }}</div>
                <p class="mt-1 text-sm text-slate-500">{{ $text }}</p>
            </div>
        @endforeach
    </div>
</div>
@endsection
