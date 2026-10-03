@props(['title', 'sub' => null])
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="min-w-0">
        <h1 class="truncate text-2xl font-bold text-slate-900">{{ $title }}</h1>
        @if ($sub) <p class="text-sm text-slate-500">{{ $sub }}</p> @endif
    </div>
    @if (! $slot->isEmpty()) <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div> @endif
</div>
