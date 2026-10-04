@extends('layouts.app')
@section('title', 'Agreements')
@section('content')
@php $tone = ['sent' => 'amber', 'signed' => 'green', 'declined' => 'red']; @endphp
<x-page-title title="Agreements" sub="Documents to read and sign." />
<div class="card">
    @forelse ($agreements as $a)
        <a href="{{ route('portal.agreements.show', $a) }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5 text-sm last:border-0 hover:bg-slate-50"><strong class="text-slate-900">{{ $a->title }}</strong><x-pill :tone="$tone[$a->status] ?? 'slate'">{{ $a->status === 'sent' ? 'Needs your signature' : ucfirst($a->status) }}</x-pill></a>
    @empty<p class="px-5 py-12 text-center text-sm text-slate-500">No agreements yet.</p>@endforelse
</div>
@endsection
