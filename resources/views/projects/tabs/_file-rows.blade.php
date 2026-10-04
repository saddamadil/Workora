@if ($files->isEmpty())<p class="px-5 py-8 text-center text-sm text-slate-500">No files on this project yet.</p>
@else
    <ul class="divide-y divide-slate-100">
        @foreach ($files as $f) @php [$icon, $tint] = $f->icon(); @endphp
            <li class="flex items-center gap-3 px-5 py-2.5 text-sm"><span class="grid size-8 place-items-center rounded-lg {{ $tint }}"><i class="bi {{ $icon }}"></i></span>
                <a href="{{ route('files.show', $f) }}" target="_blank" class="min-w-0 flex-1 truncate font-medium text-slate-800 hover:text-brand-600">{{ $f->original_name }}</a>
                @if ($f->visible_to_client)<x-pill tone="blue">Shared</x-pill>@endif
                <span class="hidden text-xs text-slate-500 sm:inline">{{ $f->humanSize() }}</span>
                @if ($f->client_id && ($toggle ?? false))<form method="POST" action="{{ route('files.toggle-client', $f) }}">@csrf<button class="text-xs text-brand-600 hover:underline">{{ $f->visible_to_client ? 'Stop sharing' : 'Share' }}</button></form>@endif
            </li>
        @endforeach
    </ul>
@endif
