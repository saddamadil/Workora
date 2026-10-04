@extends('layouts.app')
@section('title', 'Notification settings')
@section('content')
<x-page-title title="Notification settings" sub="Choose what you hear about, in the app and by email." />
<form method="POST" action="{{ route('notifications.preferences.save') }}" class="card max-w-3xl">@csrf
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Tell me about</th><th class="px-3 py-3 text-center">In the app</th><th class="px-5 py-3 text-center">By email</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($types as $key => [$label, $group, $emailDefault])
                <tr><td class="px-5 py-3"><div class="font-medium text-slate-900">{{ $label }}</div><div class="text-xs text-slate-500">{{ $group }}</div></td>
                    <td class="px-3 py-3 text-center"><input type="checkbox" name="prefs[{{ $key }}][app]" value="1" class="size-5 rounded border-slate-300" aria-label="{{ $label }} in the app" @checked(($prefs[$key]['app'] ?? true))></td>
                    <td class="px-5 py-3 text-center"><input type="checkbox" name="prefs[{{ $key }}][email]" value="1" class="size-5 rounded border-slate-300" aria-label="{{ $label }} by email" @checked(($prefs[$key]['email'] ?? $emailDefault))></td></tr>
            @endforeach
        </tbody>
    </table>
    <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4"><p class="text-xs text-slate-500">Emails are kept to what is worth interrupting you for.</p><button class="btn-primary">Save</button></div>
</form>
@endsection
