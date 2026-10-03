@extends('layouts.app')
@section('title', 'Roles & permissions')
@section('content')
<x-page-title title="Team" sub="What each role can do. This table is generated from the rules the app actually enforces." />
@include('team._tabs', ['active' => 'roles'])
<div class="card overflow-x-auto">
    <table class="w-full min-w-[720px] text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-5 py-3">Ability</th>
                @foreach ($roles as $r)<th class="px-3 py-3 text-center">{{ $r->label() }}<div class="font-normal normal-case text-slate-400">{{ $counts[$r->value] ?? 0 }} {{ ($counts[$r->value] ?? 0) == 1 ? 'person' : 'people' }}</div></th>@endforeach</tr>
        </thead>
        @foreach ($matrix as $group => $rows)
            <tbody class="divide-y divide-slate-100">
                <tr class="bg-slate-50/60"><td colspan="{{ count($roles) + 1 }}" class="px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $group }}</td></tr>
                @foreach ($rows as $row)
                    <tr><td class="px-5 py-2.5 text-slate-800">{{ $row['label'] }}</td>
                        @foreach ($roles as $r)<td class="px-3 py-2.5 text-center">@if ($row['roles'][$r->value])<i class="bi bi-check-circle-fill text-emerald-500" title="Allowed"></i><span class="sr-only">Allowed</span>@else<i class="bi bi-dash text-slate-300"></i><span class="sr-only">Not allowed</span>@endif</td>@endforeach</tr>
                @endforeach
            </tbody>
        @endforeach
    </table>
</div>
<p class="mt-4 text-sm text-slate-500">Freelancers also only ever see projects and tasks they are assigned to, and never other people's rates or budgets. Owners and admins change a person's role from the <a href="{{ route('team.members') }}" class="text-brand-600 hover:underline">Members</a> tab.</p>
@endsection
