{{-- Current image (if any) plus a file picker. The server re-encodes whatever is uploaded. --}}
@props(['name', 'label', 'src' => null, 'shape' => 'square', 'hint' => 'JPG, PNG or WebP'])
<div {{ $attributes->class(['flex items-center gap-4']) }} x-data="{ preview: @js($src) }">
    <span class="grid size-20 shrink-0 place-items-center overflow-hidden border border-slate-200 bg-slate-50 text-2xl text-slate-300 {{ $shape === 'round' ? 'rounded-full' : 'rounded-xl' }}">
        <template x-if="preview"><img :src="preview" alt="" class="size-full object-contain {{ $shape === 'round' ? 'object-cover' : '' }}"></template>
        <template x-if="! preview"><i class="bi bi-image"></i></template>
    </span>
    <div class="min-w-0">
        <label class="label" for="{{ $name }}">{{ $label }}</label>
        <input id="{{ $name }}" type="file" name="{{ $name }}" accept="image/png,image/jpeg,image/webp"
               @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview"
               class="block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}, up to 3 MB.</p>
    </div>
</div>
