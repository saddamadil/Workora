@extends('layouts.app')
@section('title', 'Shared links')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Shared links</h1>
    <p class="text-sm text-slate-500">Every link you have created. Turn one off and it stops working immediately.</p>
</div>

@if ($links->isEmpty())
    <div class="card px-6 py-12 text-center">
        <i class="bi bi-link-45deg text-4xl text-slate-300"></i>
        <p class="mt-3 font-semibold text-slate-700">You have not shared anything yet.</p>
        <p class="mt-1 text-sm text-slate-500">Open <a href="{{ route('files.index') }}" class="text-brand-600 hover:underline">My files</a> and press Share on a file.</p>
    </div>
@else
    <div class="card divide-y divide-slate-100 overflow-hidden">
        @foreach ($links as $link)
            @php
                $reason = $link->unavailableReason();
                $name = $link->file?->original_name ?? 'Deleted file';
            @endphp
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center" x-data="{ copied: false }">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="truncate font-semibold text-slate-900">{{ $name }}</span>
                        @if ($reason)
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $link->isRevoked() ? 'Off' : ($link->isExpired() ? 'Expired' : 'Unavailable') }}</span>
                        @else
                            <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Active</span>
                        @endif
                    </div>
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                        <span><i class="bi bi-eye"></i> {{ $link->view_count }} views</span>
                        <span><i class="bi bi-download"></i> {{ $link->download_count }}{{ $link->max_downloads ? ' / '.$link->max_downloads : '' }} downloads</span>
                        @if ($link->hasPassword()) <span><i class="bi bi-lock"></i> Password</span> @endif
                        <span><i class="bi bi-clock"></i> {{ $link->expires_at ? ($link->isExpired() ? 'Expired ' : 'Expires ').$link->expires_at->diffForHumans() : 'No expiry' }}</span>
                        <span>Created {{ $link->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                @unless ($link->isRevoked())
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="btn-secondary btn-sm"
                                @click="navigator.clipboard.writeText(@js($link->url())).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                            <i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i> <span x-text="copied ? 'Copied' : 'Copy link'"></span>
                        </button>
                        <form method="POST" action="{{ route('shares.destroy', $link->id) }}" onsubmit="return confirm('Turn this link off?')">@csrf @method('DELETE')
                            <button class="btn-secondary btn-sm text-red-600"><i class="bi bi-slash-circle"></i> Turn off</button>
                        </form>
                    </div>
                @endunless
            </div>
        @endforeach
    </div>
    <div class="mt-6">{{ $links->links() }}</div>
@endif
@endsection
