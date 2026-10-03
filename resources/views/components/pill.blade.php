@props(['tone' => 'slate'])
@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-600', 'green' => 'bg-emerald-50 text-emerald-700',
        'amber' => 'bg-amber-50 text-amber-700', 'red' => 'bg-red-50 text-red-700',
        'blue' => 'bg-sky-50 text-sky-700', 'indigo' => 'bg-indigo-50 text-indigo-700',
        'orange' => 'bg-orange-50 text-orange-700',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium', $tones[$tone] ?? $tones['slate']]) }}>{{ $slot }}</span>
