@php $me = auth()->id(); $fileRoute = $portal ? 'portal.files.show' : 'files.show'; $prefix = $portal ? 'portal.' : ''; @endphp
@forelse ($messages as $m)
    @php $mine = $m->user_id === $me; @endphp
    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}" id="m-{{ $m->id }}">
        <div class="max-w-[85%] sm:max-w-[70%]">
            <div class="mb-0.5 flex items-center gap-2 text-xs text-slate-500 {{ $mine ? 'justify-end' : '' }}">
                <span class="font-medium text-slate-700">{{ $mine ? 'You' : $m->user->name }}</span>
                <time datetime="{{ $m->created_at->toIso8601String() }}">{{ $m->created_at->isToday() ? $m->created_at->format('H:i') : $m->created_at->format('d M, H:i') }}</time>
                @if ($m->is_important)<i class="bi bi-star-fill text-amber-500" title="Important"></i><span class="sr-only">Important</span>@endif
            </div>
            <div class="rounded-xl border px-3.5 py-2.5 text-sm {{ $mine ? 'border-brand-100 bg-brand-50' : 'border-slate-200 bg-white' }}">
                @if ($m->invoice_id)<div class="mb-1 text-xs font-medium text-brand-700"><i class="bi bi-receipt"></i> About an invoice @unless ($portal) · <a class="underline" href="{{ route('invoices.show', $m->invoice_id) }}">open</a>@else · <a class="underline" href="{{ route('portal.invoice', $m->invoice_id) }}">open</a>@endunless</div>@endif
                @if ($m->parent)<div class="mb-2 rounded-lg border-l-2 border-slate-300 bg-slate-50 px-2.5 py-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($m->parent->body, 90) }}</div>@endif
                <div class="break-words">{{ format_message($m->body) }}</div>
                @foreach ($m->files as $f)
                    @if ($f->isImage() && $f->mime_type !== 'image/svg+xml')<a href="{{ route($fileRoute, $f) }}" target="_blank" class="mt-2 block"><img src="{{ route($fileRoute, $f) }}" alt="{{ $f->original_name }}" class="max-h-48 rounded-lg border border-slate-200"></a>
                    @else<a href="{{ route($fileRoute, $f) }}" target="_blank" class="mt-2 flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 hover:bg-slate-50"><i class="bi {{ $f->icon()[0] }}"></i><span class="truncate">{{ $f->original_name }}</span><span class="text-slate-400">{{ $f->humanSize() }}</span></a>@endif
                @endforeach
            </div>
            <div class="mt-1 flex gap-3 text-xs text-slate-400 {{ $mine ? 'justify-end' : '' }}">
                <button type="button" class="hover:text-slate-700" @click="reply = { id: '{{ $m->id }}', text: @js(\Illuminate\Support\Str::limit($m->body, 80)) }; $refs.body.focus()">Reply</button>
                <form method="POST" action="{{ route($prefix.'messages.important', $m) }}">@csrf<button class="hover:text-slate-700">{{ $m->is_important ? 'Unmark important' : 'Mark important' }}</button></form>
                @if ($portal)
                    <button type="button" class="hover:text-slate-700" @click="toTask = { id: '{{ $m->id }}', title: @js(\Illuminate\Support\Str::limit($m->body, 80, '')) }">Make a request</button>
                @elseif (! $mine || true)
                    <button type="button" class="hover:text-slate-700" @click="toTask = { id: '{{ $m->id }}', title: @js(\Illuminate\Support\Str::limit($m->body, 80, '')) }">Create task</button>
                @endif
            </div>
        </div>
    </div>
@empty
    <p class="py-16 text-center text-sm text-slate-500">No messages here yet. Say hello below.</p>
@endforelse
