@extends('layouts.app')
@section('title', $agreement->title)
@section('content')
@php $tone = ['draft' => 'slate', 'sent' => 'amber', 'signed' => 'green', 'declined' => 'red', 'void' => 'slate']; @endphp
<div class="mb-2 text-sm"><a href="{{ route('agreements.index') }}" class="text-brand-600 hover:underline"><i class="bi bi-arrow-left"></i> Agreements</a></div>
<x-page-title :title="$agreement->title" :sub="$agreement->client?->name.($agreement->project ? ' · '.$agreement->project->name : '')">
    <x-pill :tone="$tone[$agreement->status] ?? 'slate'">{{ ucfirst($agreement->status) }}</x-pill>
    <a href="{{ route('agreements.pdf', $agreement) }}" target="_blank" class="btn-secondary btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    @if ($agreement->status === 'draft')<a href="{{ route('agreements.edit', $agreement) }}" class="btn-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>@endif
    @if (in_array($agreement->status, ['draft', 'sent'], true))<form method="POST" action="{{ route('agreements.send', $agreement) }}">@csrf<button class="btn-primary btn-sm"><i class="bi bi-send"></i> {{ $agreement->status === 'sent' ? 'Send again' : 'Send for signature' }}</button></form>@endif
    @if ($agreement->status !== 'signed' && $agreement->status !== 'void')<form method="POST" action="{{ route('agreements.void', $agreement) }}" onsubmit="return confirm('Void this agreement?')">@csrf<button class="btn-secondary btn-sm">Void</button></form>@endif
    @if (in_array($agreement->status, ['draft', 'void', 'declined'], true))<form method="POST" action="{{ route('agreements.destroy', $agreement) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn-secondary btn-sm text-red-600">Delete</button></form>@endif
</x-page-title>
<div class="grid gap-6 lg:grid-cols-3"><div class="card p-6 lg:col-span-2"><div class="whitespace-pre-line text-sm leading-relaxed text-slate-800">{{ $agreement->body }}</div></div>@include('agreements._record')</div>
@endsection
