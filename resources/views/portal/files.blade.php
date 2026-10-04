@extends('layouts.app')
@section('title', 'Files')
@section('content')
<x-page-title title="Files" sub="Shared with you by your freelancer, and what you send back." />
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        @php $grouped = $files->groupBy(fn ($f) => $f->folder ?: 'Documents'); @endphp
        @forelse ($grouped as $folder => $list)
            <div class="border-b border-slate-100 bg-slate-50 px-5 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500"><i class="bi bi-folder2 me-1"></i>{{ $folder }}</div>
            @foreach ($list as $f)
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0">
                    <i class="bi {{ $f->icon()[0] }} text-lg text-slate-400"></i>
                    <div class="min-w-0 flex-1"><a href="{{ route('portal.files.show', $f) }}" target="_blank" class="block truncate font-medium text-slate-900 hover:text-brand-600">{{ $f->original_name }}</a>
                        <div class="text-xs text-slate-500">{{ $f->project?->name ?: 'General' }} · {{ $f->uploadedBy?->name }} · {{ $f->created_at->format('d M Y') }} · v{{ $f->version }}</div></div>
                    <span class="hidden text-xs text-slate-500 sm:inline">{{ $f->humanSize() }}</span>
                    <a href="{{ route('portal.files.download', $f) }}" class="btn-secondary btn-sm" aria-label="Download {{ $f->original_name }}"><i class="bi bi-download"></i></a>
                </div>
            @endforeach
        @empty
            <p class="px-5 py-12 text-center text-sm text-slate-500">No files yet. Upload one, or wait for your freelancer to share something.</p>
        @endforelse
    </div>
    <form method="POST" action="{{ route('portal.files.store') }}" enctype="multipart/form-data" class="card h-fit space-y-3 p-5">@csrf
        <h2 class="font-semibold text-slate-900">Upload a file</h2>
        <div><label class="label" for="f">Files</label><input id="f" type="file" name="files[]" multiple required class="input"></div>
        <div><label class="label" for="fp">Project</label><select id="fp" name="project_id" class="input"><option value="">General</option>@foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
        <button class="btn-primary w-full"><i class="bi bi-cloud-arrow-up"></i> Upload</button>
        @error('files.*')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    </form>
</div>
@endsection
