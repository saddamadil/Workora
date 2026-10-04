@php
    $solo = ($org?->mode ?? 'team') === 'solo';
    $isFreelancer = ($role?->isFreelancer() ?? false) || $solo;
    $tabs = [
        ['overview', 'team.index', 'Overview', 'bi-speedometer2', ! $isFreelancer],
        ['members', 'team.members', $isFreelancer ? 'Company' : 'Members', 'bi-people', ! $solo],
        ['invitations', 'team.invitations', 'Invitations', 'bi-envelope-open', ! $isFreelancer],
        ['roles', 'team.roles', 'Roles & permissions', 'bi-shield-check', ! $isFreelancer],
        ['payments', 'team.payment-profiles', 'Payment profiles', 'bi-bank', $isFreelancer || Gate::allows('pay') || Gate::allows('manage-team')],
        ['invoices', 'team.invoices', 'Invoices', 'bi-receipt', $isFreelancer || Gate::allows('see-money')],
    ];
@endphp
<nav class="-mx-1 mb-6 flex gap-1 overflow-x-auto border-b border-slate-200 px-1" aria-label="Team sections">
    @foreach ($tabs as [$key, $route, $label, $icon, $show])
        @continue(! $show)
        <a href="{{ route($route) }}" @if ($active === $key) aria-current="page" @endif
           class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium {{ $active === $key ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }}">
            <i class="bi {{ $icon }}"></i> {{ $label }}
        </a>
    @endforeach
</nav>
