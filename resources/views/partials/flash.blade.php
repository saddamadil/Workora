@if (session('status'))
    <div x-data="{ show: true }" x-show="show" class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
        <i class="bi bi-check-circle-fill mt-0.5"></i>
        <div class="flex-1">{{ session('status') }}</div>
        <button type="button" @click="show = false" class="text-emerald-700/70 hover:text-emerald-900" aria-label="Dismiss"><i class="bi bi-x-lg"></i></button>
    </div>
@endif
@if (session('error') || $errors->any())
    <div x-data="{ show: true }" x-show="show" class="mb-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
        <div class="flex-1">
            {{ session('error') }}
            @foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach
        </div>
        <button type="button" @click="show = false" class="text-red-700/70 hover:text-red-900" aria-label="Dismiss"><i class="bi bi-x-lg"></i></button>
    </div>
@endif
