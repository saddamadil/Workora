@extends('layouts.app')
@section('title', 'Google Drive')
@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Google Drive</h1>
        <p class="text-sm text-slate-500">Bring files in from Drive, or save your Workora files back to it.</p>
    </div>
    @if ($connection)
        <div class="flex items-center gap-3 text-sm">
            <span class="text-slate-500"><i class="bi bi-check-circle-fill text-emerald-500"></i> {{ $connection->google_email ?: 'Connected' }}</span>
            <form method="POST" action="{{ route('drive.disconnect') }}" onsubmit="return confirm('Disconnect Google Drive? Files you already imported stay in Workora.')">@csrf
                <button class="btn-secondary btn-sm">Disconnect</button>
            </form>
        </div>
    @endif
</div>

@if (! $configured)
    <div class="card max-w-2xl p-6">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-amber-50 text-xl text-amber-600"><i class="bi bi-gear"></i></span>
            <h2 class="text-lg font-bold text-slate-900">Google Drive is not set up on this server yet</h2>
        </div>
        <p class="mt-3 text-sm text-slate-600">An administrator needs to create Google credentials once. It takes about five minutes:</p>
        <ol class="mt-3 list-decimal space-y-2 ps-5 text-sm text-slate-700">
            <li>In <a class="text-brand-600 hover:underline" href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" rel="noopener">Google Cloud Console</a>, enable the <strong>Google Drive API</strong>.</li>
            <li>Under <em>APIs &amp; Services &rarr; Credentials</em>, create an <strong>OAuth client ID</strong> of type <em>Web application</em>.</li>
            <li>Add this as an authorised redirect URI:
                <code class="mt-1 block break-all rounded-lg bg-slate-100 px-3 py-2 text-xs">{{ $redirectUri }}</code></li>
            <li>Put the client ID and secret in <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">.env</code>:
                <code class="mt-1 block whitespace-pre rounded-lg bg-slate-100 px-3 py-2 text-xs">GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...</code></li>
            <li>Reload this page.</li>
        </ol>
    </div>
@elseif (! $connection)
    <div class="card mx-auto max-w-lg p-8 text-center">
        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-blue-50 text-3xl text-blue-600"><i class="bi bi-google"></i></span>
        <h2 class="mt-4 text-lg font-bold text-slate-900">Connect your Google Drive</h2>
        <p class="mt-2 text-sm text-slate-500">Workora will be able to list your Drive files so you can import them, and to save copies you choose. Disconnect whenever you like.</p>
        <a href="{{ route('drive.connect') }}" class="btn-primary mt-6 px-6"><i class="bi bi-google"></i> Connect with Google</a>
    </div>
@else
    @php
        $nav = fn (array $q) => route('drive.index', array_filter($q, fn ($v) => $v !== null && $v !== ''));
        $trailJson = fn (array $t) => $t ? json_encode($t) : null;
    @endphp

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <nav class="flex flex-wrap items-center gap-1.5 text-sm" aria-label="Folder path">
            <a href="{{ route('drive.index') }}" class="font-medium text-brand-600 hover:underline"><i class="bi bi-hdd"></i> My Drive</a>
            @foreach ($trail as $i => [$id, $label])
                <i class="bi bi-chevron-right text-xs text-slate-400"></i>
                <a href="{{ $nav(['folder' => $id, 'trail' => $trailJson(array_slice($trail, 0, $i))]) }}" class="{{ $loop->last ? 'font-semibold text-slate-800' : 'text-brand-600 hover:underline' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="{{ route('drive.index') }}" class="relative w-full sm:w-64">
            <i class="bi bi-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search Drive" class="input pl-10" aria-label="Search Google Drive">
        </form>
    </div>

    @if ($error)
        <div class="card p-6 text-center text-sm text-red-700">{{ $error }}</div>
    @elseif (empty($items))
        <div class="card px-6 py-12 text-center">
            <i class="bi bi-folder2-open text-4xl text-slate-300"></i>
            <p class="mt-3 font-semibold text-slate-700">{{ $search ? 'No Drive files match that search.' : 'This folder is empty.' }}</p>
        </div>
    @else
        <form method="POST" action="{{ route('drive.import') }}" x-data="{ picked: [] }">
            @csrf
            <div class="card divide-y divide-slate-100 overflow-hidden">
                @foreach ($items as $item)
                    @php
                        $isFolder = $item['mimeType'] === \App\Services\GoogleDrive::FOLDER;
                        $native = str_starts_with($item['mimeType'], 'application/vnd.google-apps.');
                        $importable = ! $isFolder && (! $native || isset(\App\Services\GoogleDrive::EXPORTS[$item['mimeType']]));
                    @endphp
                    <div class="flex items-center gap-3 px-4 py-3">
                        @if ($importable)
                            <input type="checkbox" name="ids[]" value="{{ $item['id'] }}" x-model="picked" class="size-4 rounded border-slate-300" aria-label="Select {{ $item['name'] }}">
                        @else
                            <span class="size-4"></span>
                        @endif
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $isFolder ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-600' }}">
                            <i class="bi {{ $isFolder ? 'bi-folder-fill' : ($native ? 'bi-file-earmark-richtext' : 'bi-file-earmark') }}"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            @if ($isFolder)
                                <a href="{{ $nav(['folder' => $item['id'], 'trail' => $trailJson(array_merge($trail, [[$item['id'], $item['name']]]))]) }}" class="block truncate font-medium text-slate-900 hover:text-brand-600">{{ $item['name'] }}</a>
                            @else
                                <div class="truncate font-medium text-slate-900">{{ $item['name'] }}</div>
                            @endif
                            <div class="text-xs text-slate-500">
                                @if (isset($item['size'])) {{ \App\Models\File::formatBytes((int) $item['size']) }} · @endif
                                @if (! empty($item['modifiedTime'])) {{ \Illuminate\Support\Carbon::parse($item['modifiedTime'])->diffForHumans() }} @endif
                                @if ($native && ! $importable) · Not importable @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="sticky bottom-4 mt-4 flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-lg" x-show="picked.length" x-cloak>
                <span class="px-2 text-sm text-slate-600"><strong x-text="picked.length"></strong> selected</span>
                <button class="btn-primary"><i class="bi bi-cloud-download"></i> Import to Workora</button>
            </div>
        </form>

        @if ($next)
            <div class="mt-4 text-center">
                <a class="btn-secondary" href="{{ $nav(['folder' => $folder !== 'root' ? $folder : null, 'trail' => $trailJson($trail), 'q' => $search, 'page' => $next]) }}">Load more</a>
            </div>
        @endif
    @endif
@endif
@endsection
