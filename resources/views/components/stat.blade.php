@props(['label', 'value', 'icon' => 'bi-circle', 'hint' => null, 'href' => null, 'tone' => 'brand'])
@php
    $tones = ['brand' => 'bg-brand-50 text-brand-600', 'green' => 'bg-emerald-50 text-emerald-600', 'amber' => 'bg-amber-50 text-amber-600', 'red' => 'bg-red-50 text-red-600', 'slate' => 'bg-slate-100 text-slate-600'];
@endphp
<{{ $href ? 'a href='.$href : 'div' }} class="card flex items-start gap-3 p-4 {{ $href ? 'transition hover:border-brand-500' : '' }}">
    <span class="grid size-10 shrink-0 place-items-center rounded-xl text-lg {{ $tones[$tone] ?? $tones['brand'] }}"><i class="bi {{ $icon }}"></i></span>
    <span class="min-w-0">
        <span class="block text-sm text-slate-500">{{ $label }}</span>
        <span class="block truncate text-xl font-bold text-slate-900">{{ $value }}</span>
        @if ($hint) <span class="block text-xs text-slate-400">{{ $hint }}</span> @endif
    </span>
</{{ $href ? 'a' : 'div' }}>
