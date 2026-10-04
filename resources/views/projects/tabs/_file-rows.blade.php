@if ($files->isEmpty())<p class="px-5 py-8 text-center text-sm text-slate-500">No files on this project yet.</p>
@else
    <ul class="divide-y divide-slate-100">
        @foreach ($files as $f) @php [$icon, $tint] = $f->icon(); @endphp
            <li class="flex items-center gap-3 px-5 py-2.5 text-sm"><span class="grid size-8 place-items-center rounded-lg {{ $tint }}"><i class="bi {{ $icon }}"></i></span>
                <a href="{{ route('files.show', $f) }}" target="_blank" class="min-w-0 flex-1 truncate font-medium text-slate-800 hover:text-brand-600">{{ $f->original_name }}</a>
                @if ($f->version > 1)<x-pill tone="slate">v{{ $f->version }}</x-pill>@endif
                @if ($f->visible_to_client)<x-pill tone="blue">Shared</x-pill>@endif
                <span class="hidden text-xs text-slate-500 sm:inline">{{ $f->humanSize() }}</span>
                @if ($f->client_id && ($toggle ?? false))<form method="POST" action="{{ route('files.toggle-client', $f) }}">@csrf<button class="text-xs text-brand-600 hover:underline">{{ $f->visible_to_client ? 'Stop sharing' : 'Share' }}</button></form>@endif
                @if ($toggle ?? false)
                <details class="relative"><summary class="cursor-pointer list-none text-xs text-slate-500 hover:text-slate-800" title="Versions, move"><i class="bi bi-three-dots"></i></summary>
                    <div class="card absolute end-0 z-20 mt-2 w-72 space-y-3 p-3 text-xs">
                        <form method="POST" action="{{ route('files.version', $f) }}" enctype="multipart/form-data" class="space-y-2">@csrf
                            <label class="label" for="nv-{{ $f->id }}">Upload a new version</label>
                            <input id="nv-{{ $f->id }}" type="file" name="file" required class="input"><button class="btn-secondary btn-sm w-full"><i class="bi bi-cloud-arrow-up"></i> Upload version {{ $f->version + 1 }}</button>
                        </form>
                        <form method="POST" action="{{ route('files.update', $f) }}" class="flex gap-2">@csrf @method('PATCH')
                            <input name="folder" value="{{ $f->folder }}" list="folder-list" maxlength="60" class="input" aria-label="Folder"><button class="btn-secondary btn-sm">Move</button>
                        </form>
                        @if ($f->version > 1)
                            <div><p class="mb-1 font-semibold text-slate-700">Earlier versions</p>
                                @foreach ($f->history()->skip(1) as $old)<a href="{{ route('files.download', $old) }}" class="block truncate text-brand-600 hover:underline">v{{ $old->version }} · {{ $old->created_at->format('d M Y') }} · {{ $old->humanSize() }}</a>@endforeach</div>
                        @endif
                    </div></details>
                @endif
            </li>
        @endforeach
    </ul>
@endif
