<div class="grid gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Files</h2></div>
        @php $grouped = $files->groupBy(fn ($f) => $f->folder ?: 'Documents'); @endphp
        @forelse ($grouped as $folder => $list)
            <div class="border-b border-slate-100 bg-slate-50 px-5 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500"><i class="bi bi-folder2 me-1"></i>{{ $folder }}</div>
            @include('projects.tabs._file-rows', ['files' => $list, 'toggle' => true])
        @empty
            <p class="px-5 py-10 text-center text-sm text-slate-500">No files yet. Upload reports, research, creative work and deliverables here.</p>
        @endforelse
    </div>
    @can('create', \App\Models\File::class)
    <form method="POST" action="{{ route('projects.files.store', $project) }}" enctype="multipart/form-data" class="card h-fit space-y-3 p-5">@csrf
        <h2 class="font-semibold text-slate-900">Upload</h2>
        <div><label class="label" for="pf-files">Files</label><input id="pf-files" type="file" name="files[]" multiple required class="input"></div>
        <div><label class="label" for="pf-folder">Folder</label><input id="pf-folder" name="folder" list="folder-list" value="Documents" maxlength="60" class="input"><p class="mt-1 text-xs text-slate-500">Pick one or type a new folder name.</p></div>
        <datalist id="folder-list">@foreach (collect($folders)->merge($files->pluck('folder'))->filter()->unique() as $f)<option value="{{ $f }}">@endforeach</datalist>
        @if ($project->client_id)
            <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="visible_to_client" value="1" class="mt-1 rounded border-slate-300"> <span>Share with {{ $project->client?->name }}<span class="block text-xs text-slate-500">Files are private until you share them.</span></span></label>
        @endif
        <button class="btn-primary w-full"><i class="bi bi-cloud-arrow-up"></i> Upload</button>
    </form>
    @endcan
</div>
