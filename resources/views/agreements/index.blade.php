@extends('layouts.app')
@section('title', 'Agreements')
@section('content')
@php $tone = ['draft' => 'slate', 'sent' => 'amber', 'signed' => 'green', 'declined' => 'red', 'void' => 'slate']; @endphp
<x-page-title title="Agreements" sub="Documents your client signs online by typing their name."><a href="{{ route('agreements.create') }}" class="btn-primary"><i class="bi bi-plus-lg"></i> New agreement</a></x-page-title>
<div class="card">
    @forelse ($agreements as $a)
        <a href="{{ route('agreements.show', $a) }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 text-sm last:border-0 hover:bg-slate-50">
            <span><strong class="text-slate-900">{{ $a->title }}</strong><span class="block text-slate-500">{{ $a->client?->name }}@if ($a->signed_at) · signed {{ $a->signed_at->format('d M Y') }}@endif</span></span>
            <x-pill :tone="$tone[$a->status] ?? 'slate'">{{ ucfirst($a->status) }}</x-pill>
        </a>
    @empty<p class="px-5 py-12 text-center text-sm text-slate-500">No agreements yet. Start from a template such as a services agreement or an NDA.</p>@endforelse
</div>
@endsection
