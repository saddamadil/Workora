@php
    $cards = [
        ['Total invoiced', 'invoiced', 'bi-receipt', 'brand'],
        ['Paid', 'paid', 'bi-check-circle', 'green'],
        ['Outstanding', 'outstanding', 'bi-hourglass-split', 'amber'],
        ['Overdue', 'overdue', 'bi-exclamation-circle', 'red'],
    ];
    $fmt = fn (array $by) => $by ? collect($by)->map(fn ($minor, $cur) => money($minor, $cur))->implode(' + ') : money(0, $org->base_currency);
@endphp
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as [$label, $key, $icon, $tone])
        <x-stat :label="$label" :value="$fmt($stats[$key])" :icon="$icon" :tone="empty($stats[$key]) ? 'slate' : $tone" />
    @endforeach
</div>
