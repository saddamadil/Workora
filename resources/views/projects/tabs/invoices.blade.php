<div class="mb-4 flex justify-end">@can('create', \App\Models\Invoice::class)<a href="{{ route('invoices.create', ['client' => $project->client_id, 'project' => $project->id]) }}" class="btn-primary btn-sm"><i class="bi bi-receipt"></i> Create invoice</a>@endcan</div>
<div class="card divide-y divide-slate-100">
    @forelse ($invoices as $i)
        <a href="{{ route('invoices.show', $i) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 text-sm hover:bg-slate-50"><span class="font-medium text-slate-900">{{ $i->number }} <span class="font-normal text-slate-500">· due {{ $i->due_date->format('d M Y') }}</span></span><span>{{ money($i->total_minor, $i->currency) }} · <x-pill>{{ ucfirst($i->displayStatus()) }}</x-pill></span></a>
    @empty<p class="px-5 py-10 text-center text-sm text-slate-500">No invoices for this project yet. Create your first invoice in a few clicks.</p>@endforelse
</div>
