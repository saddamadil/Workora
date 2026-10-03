@extends('layouts.app')
@section('title', 'My files')

@section('content')
@php
    $canEdit = auth()->user()->can('create', \App\Models\File::class);
    $query = fn (array $extra = []) => array_filter(array_merge(request()->only('q', 'folder', 'type', 'sort'), $extra), fn ($v) => $v !== null && $v !== '');
@endphp

<div x-data="shareDialog()" @share-file.window="show($event.detail)">

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">My files</h1>
            <p class="text-sm text-slate-500">Upload, preview and share everything from one place.</p>
        </div>
        <form method="GET" action="{{ route('files.index') }}" class="relative w-full sm:w-72">
            @foreach (request()->only('folder', 'type', 'sort') as $k => $v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endforeach
            <i class="bi bi-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search files" class="input pl-10" aria-label="Search files">
        </form>
    </div>

    {{-- Upload --}}
    @if ($canEdit)
        <section x-data="uploader({ url: '{{ route('files.store') }}', maxMb: {{ config('workora.max_upload_mb') }} })"
                 @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="drop($event)"
                 class="mb-6">
            <label :class="dragging ? 'border-brand-500 bg-brand-50' : 'border-slate-300 bg-white hover:border-brand-500 hover:bg-brand-50/40'"
                   class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 {{ $files->isEmpty() && ! $search ? 'py-14' : 'py-7' }} text-center transition">
                <span class="mb-3 grid size-12 place-items-center rounded-full bg-brand-100 text-2xl text-brand-600"><i class="bi bi-cloud-arrow-up"></i></span>
                <span class="font-semibold text-slate-800">Drop files here, or <span class="text-brand-600 underline">browse</span></span>
                <span class="mt-1 text-sm text-slate-500">Documents, images, anything up to {{ config('workora.max_upload_mb') }} MB each</span>
                <input type="file" multiple class="sr-only" @change="pick($event)">
            </label>

            <div x-show="queue.length" x-cloak class="card mt-3 divide-y divide-slate-100">
                <template x-for="item in queue" :key="item.id">
                    <div class="flex items-center gap-3 px-4 py-3">
                        <i class="bi text-lg" :class="{
                            'bi-check-circle-fill text-emerald-500': item.state === 'done',
                            'bi-exclamation-circle-fill text-red-500': item.state === 'error',
                            'bi-file-earmark text-slate-400': item.state === 'waiting' || item.state === 'uploading' }"></i>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-slate-800" x-text="item.name"></div>
                            <div x-show="item.state === 'error'" class="text-xs text-red-600" x-text="item.error"></div>
                            <div x-show="item.state === 'uploading' || item.state === 'waiting'" class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-brand-600 transition-all" :style="`width:${item.progress}%`"></div>
                            </div>
                        </div>
                        <span class="text-xs text-slate-500" x-show="item.state === 'uploading'" x-text="item.progress + '%'"></span>
                        <button type="button" x-show="item.state === 'error'" @click="dismiss(item.id)" class="text-slate-400 hover:text-slate-600" aria-label="Dismiss"><i class="bi bi-x-lg"></i></button>
                    </div>
                </template>
            </div>
        </section>
    @endif

    {{-- Filters --}}
    <div class="mb-5 flex flex-wrap items-center gap-2">
        <a href="{{ route('files.index', $query(['type' => null, 'folder' => null])) }}" class="chip {{ ! $type && ! $folder ? 'chip-active' : '' }}">All</a>
        <a href="{{ route('files.index', $query(['type' => 'images', 'folder' => null])) }}" class="chip {{ $type === 'images' ? 'chip-active' : '' }}"><i class="bi bi-image"></i> Images</a>
        <a href="{{ route('files.index', $query(['type' => 'documents', 'folder' => null])) }}" class="chip {{ $type === 'documents' ? 'chip-active' : '' }}"><i class="bi bi-file-earmark-text"></i> Documents</a>
        {{-- Images and Documents already have their own type chips above. --}}
        @foreach ($folders->reject(fn ($f) => in_array(strtolower($f), ['images', 'documents'])) as $name)
            <a href="{{ route('files.index', $query(['folder' => $name, 'type' => null])) }}" class="chip {{ $folder === $name ? 'chip-active' : '' }}"><i class="bi bi-folder"></i> {{ $name }}</a>
        @endforeach
        <div class="ms-auto">
            <a href="{{ route('files.index', $query(['sort' => $sort === 'name' ? null : 'name'])) }}" class="text-sm text-slate-500 hover:text-slate-800">
                <i class="bi bi-sort-alpha-down"></i> {{ $sort === 'name' ? 'Sorted by name' : 'Newest first' }}
            </a>
        </div>
    </div>

    {{-- Files --}}
    @if ($files->isEmpty())
        <div class="card px-6 py-12 text-center">
            <i class="bi bi-inbox text-4xl text-slate-300"></i>
            <p class="mt-3 font-semibold text-slate-700">{{ $search || $type || $folder ? 'Nothing matches those filters.' : 'No files yet.' }}</p>
            <p class="mt-1 text-sm text-slate-500">
                @if ($search || $type || $folder)
                    <a href="{{ route('files.index') }}" class="text-brand-600 hover:underline">Clear filters</a>
                @else
                    Drop something above to get started.
                @endif
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($files as $file)
                @php [$icon, $tint] = $file->icon(); @endphp
                <article class="card group flex flex-col overflow-hidden">
                    <a href="{{ route('files.show', $file) }}" target="_blank" rel="noopener"
                       class="grid h-36 place-items-center overflow-hidden bg-slate-50">
                        @if ($file->isImage() && $file->isInlineViewable())
                            <img src="{{ route('files.show', $file) }}" alt="{{ $file->original_name }}" loading="lazy" class="size-full object-cover transition group-hover:scale-105">
                        @else
                            <span class="grid size-16 place-items-center rounded-2xl text-3xl {{ $tint }}"><i class="bi {{ $icon }}"></i></span>
                        @endif
                    </a>
                    <div class="flex flex-1 flex-col p-4">
                        <div class="truncate font-semibold text-slate-900" title="{{ $file->original_name }}">{{ $file->original_name }}</div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            {{ $file->humanSize() }} · {{ $file->created_at->diffForHumans() }}
                        </div>
                        <div class="mt-1 flex flex-wrap gap-1.5 text-xs">
                                            @if ($file->active_links_count) <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700"><i class="bi bi-link-45deg"></i> Shared</span> @endif
                            @if ($file->folder) <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">{{ $file->folder }}</span> @endif
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            @can('share', $file)
                                <button type="button" class="btn-primary btn-sm flex-1"
                                        @click="$dispatch('share-file', { name: @js($file->original_name), shareUrl: @js(route('files.share', $file)) })">
                                    <i class="bi bi-share"></i> Share
                                </button>
                            @endcan
                            <a href="{{ route('files.download', $file) }}" class="btn-secondary btn-sm" title="Download" aria-label="Download {{ $file->original_name }}"><i class="bi bi-download"></i></a>

                            <div x-data="{ open: false, rename: false }" class="relative" @keydown.escape="open = false">
                                <button type="button" @click="open = !open" class="btn-secondary btn-sm" aria-label="More actions" :aria-expanded="open"><i class="bi bi-three-dots"></i></button>
                                <div x-show="open" x-cloak @click.outside="open = false" class="absolute bottom-full right-0 z-20 mb-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                                    <a href="{{ route('files.show', $file) }}" target="_blank" rel="noopener" class="menu-item"><i class="bi bi-box-arrow-up-right"></i> Open</a>
                                    @can('update', $file)
                                        <button type="button" @click="rename = true; open = false" class="menu-item"><i class="bi bi-pencil"></i> Rename</button>
                                    @endcan
                                    @can('delete', $file)
                                        <form method="POST" action="{{ route('files.destroy', $file) }}" onsubmit="return confirm('Delete this file? Its share links will stop working.')">@csrf @method('DELETE')
                                            <button class="menu-item text-red-600"><i class="bi bi-trash"></i> Delete</button>
                                        </form>
                                    @endcan
                                </div>

                                {{-- Rename dialog --}}
                                <div x-show="rename" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4" @keydown.escape.window="rename = false">
                                    <form method="POST" action="{{ route('files.update', $file) }}" @click.outside="rename = false" class="card w-full max-w-sm space-y-4 p-5">
                                        @csrf @method('PATCH')
                                        <h2 class="font-bold text-slate-900">Rename file</h2>
                                        <input name="original_name" value="{{ $file->original_name }}" class="input" required maxlength="200" aria-label="File name">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" @click="rename = false" class="btn-secondary btn-sm">Cancel</button>
                                            <button class="btn-primary btn-sm">Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">{{ $files->links() }}</div>
    @endif

    {{-- Share dialog --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-slate-900/40 p-4" @keydown.escape.window="open = false">
        <div @click.outside="open = false" class="card w-full max-w-md p-6" role="dialog" aria-modal="true" aria-labelledby="share-title">
            <div class="mb-1 flex items-start justify-between gap-3">
                <h2 id="share-title" class="text-lg font-bold text-slate-900">Share a link</h2>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-600" aria-label="Close"><i class="bi bi-x-lg"></i></button>
            </div>
            <p class="truncate text-sm text-slate-500" x-text="file?.name"></p>

            <template x-if="!url">
                <form @submit.prevent="create" class="mt-5 space-y-4">
                    <div>
                        <label class="label" for="s-expiry">Link expires</label>
                        <select id="s-expiry" x-model="form.expires_in_days" class="input">
                            <option value="">Never</option>
                            <option value="1">After 1 day</option>
                            <option value="7">After 7 days</option>
                            <option value="30">After 30 days</option>
                        </select>
                    </div>
                    <div>
                        <label class="label" for="s-pass">Password <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="s-pass" type="text" x-model="form.password" class="input" minlength="4" placeholder="Ask for a password" autocomplete="off">
                    </div>
                    <div>
                        <label class="label" for="s-max">Download limit <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="s-max" type="number" min="1" x-model="form.max_downloads" class="input" placeholder="Unlimited">
                    </div>
                    <p x-show="error" x-text="error" class="text-sm text-red-600"></p>
                    <button class="btn-primary w-full" :disabled="busy"><i class="bi bi-link-45deg"></i> <span x-text="busy ? 'Creating…' : 'Create link'"></span></button>
                </form>
            </template>

            <template x-if="url">
                <div class="mt-5">
                    <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-2 pl-3">
                        <input x-ref="link" readonly :value="url" class="min-w-0 flex-1 bg-transparent text-sm text-slate-800 focus:outline-none" @focus="$el.select()" aria-label="Share link">
                        <button type="button" @click="copy" class="btn-primary btn-sm"><i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i> <span x-text="copied ? 'Copied' : 'Copy'"></span></button>
                    </div>
                    <p class="mt-3 text-sm text-slate-500">Anyone with this link can open the file<span x-show="form.password"> after entering the password</span>. Turn it off any time from <a href="{{ route('shares.index') }}" class="text-brand-600 hover:underline">Shared links</a>.</p>
                    <button type="button" @click="open = false" class="btn-secondary mt-4 w-full">Done</button>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
