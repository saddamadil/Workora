@extends('layouts.guest')
@section('title', $file->original_name.' · Workora')
@section('width', 'max-w-xl')
@section('content')
@php [$icon, $tint] = $file->icon(); @endphp
<div class="card overflow-hidden">
    @if ($file->isInlineViewable() && $file->isImage())
        <img src="{{ route('share.preview', $link->token) }}" alt="{{ $file->original_name }}" class="max-h-96 w-full bg-slate-50 object-contain">
    @elseif ($file->mime_type === 'application/pdf')
        <iframe src="{{ route('share.preview', $link->token) }}" title="{{ $file->original_name }}" class="h-96 w-full border-b border-slate-200"></iframe>
    @else
        <div class="grid h-40 place-items-center bg-slate-50"><span class="grid size-20 place-items-center rounded-3xl text-4xl {{ $tint }}"><i class="bi {{ $icon }}"></i></span></div>
    @endif

    <div class="p-6">
        <h1 class="break-words text-lg font-bold text-slate-900">{{ $file->original_name }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $file->humanSize() }} · shared by {{ $owner }}</p>
        <a href="{{ route('share.download', $link->token) }}" class="btn-primary mt-5 w-full py-3"><i class="bi bi-download"></i> Download</a>
        @if ($link->expires_at)
            <p class="mt-3 text-center text-xs text-slate-400">Link expires {{ $link->expires_at->diffForHumans() }}</p>
        @endif
    </div>
</div>
<p class="mt-5 text-center text-xs text-slate-400">Shared with <a href="{{ url('/') }}" class="hover:underline">Workora</a></p>
@endsection
