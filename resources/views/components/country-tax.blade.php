{{-- A country dropdown with the tax/registration boxes that country uses. Shows and hides boxes as the country changes. --}}
@props(['country' => null, 'values' => [], 'label' => 'Country', 'name' => 'country_code'])
<div x-data="{ country: @js(old($name, $country) ?: ''), vals: @js(old('tax_ids', $values ?: new stdClass)), all: @js(\App\Support\TaxFields::allForForms()),
              get fields() { return this.all[this.country] || this.all.default } }" {{ $attributes->class(['grid gap-4 sm:grid-cols-2']) }}>
    <div>
        <label class="label" for="{{ $name }}">{{ $label }}</label>
        <select id="{{ $name }}" name="{{ $name }}" x-model="country" class="input">
            <option value="">Choose…</option>
            @foreach (\App\Support\Countries::LIST as $code => $cname)<option value="{{ $code }}">{{ $cname }}</option>@endforeach
        </select>
    </div>
    <div class="sm:col-span-2 grid gap-4 sm:grid-cols-2" x-show="country" x-cloak>
        <template x-for="f in fields" :key="country + f.key">
            <div>
                <label class="label" :for="'tax_' + f.key" x-text="f.label"></label>
                <input :id="'tax_' + f.key" :name="'tax_ids[' + f.key + ']'" x-model="vals[f.key]" class="input uppercase" :placeholder="f.hint" autocomplete="off">
            </div>
        </template>
    </div>
</div>
