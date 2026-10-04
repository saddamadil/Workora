{{-- Line items editor shared by quotes. $rows is an array of description / quantity / unit_rate (major units). --}}
<div x-data="{ rows: @js($rows), add() { this.rows.push({ description: '', quantity: 1, unit_rate: '' }); }, total() { return this.rows.reduce((s, r) => s + (parseFloat(r.quantity) || 0) * (parseFloat(r.unit_rate) || 0), 0); } }">
    <div class="space-y-2">
        <template x-for="(r, i) in rows" :key="i">
            <div class="grid grid-cols-12 items-start gap-2">
                <input :name="`items[${i}][description]`" x-model="r.description" required maxlength="250" placeholder="Description" aria-label="Description" class="input col-span-12 sm:col-span-6">
                <input :name="`items[${i}][quantity]`" x-model="r.quantity" type="number" step="0.01" min="0.01" required aria-label="Quantity" class="input col-span-4 sm:col-span-2">
                <input :name="`items[${i}][unit_rate]`" x-model="r.unit_rate" type="number" step="0.01" min="0" required placeholder="Rate" aria-label="Rate" class="input col-span-6 sm:col-span-3">
                <button type="button" @click="rows.length > 1 && rows.splice(i, 1)" class="col-span-2 sm:col-span-1 pt-2 text-slate-400 hover:text-red-600" aria-label="Remove line"><i class="bi bi-x-lg"></i></button>
            </div>
        </template>
    </div>
    <div class="mt-3 flex items-center justify-between"><button type="button" @click="add()" class="btn-secondary btn-sm"><i class="bi bi-plus-lg"></i> Add line</button><span class="text-sm text-slate-500">Subtotal <strong class="text-slate-900" x-text="total().toFixed(2)"></strong></span></div>
</div>
