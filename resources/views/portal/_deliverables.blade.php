@php $tone = ['in_review' => 'amber', 'approved' => 'green', 'changes_requested' => 'orange']; @endphp
@if ($deliverables->isNotEmpty())
<section class="card" aria-labelledby="dv">
    <div class="border-b border-slate-100 px-5 py-3"><h2 id="dv" class="font-semibold text-slate-900">Deliverables</h2></div>
    @foreach ($deliverables as $d)
        <div class="border-b border-slate-100 px-5 py-4 text-sm last:border-0" x-data="{ changes: false }">
            <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-semibold text-slate-900">{{ $d->title }}</span><x-pill :tone="$tone[$d->status] ?? 'slate'">{{ $d->status === 'in_review' ? 'Ready for your review' : \App\Models\Deliverable::LABELS[$d->status] }}</x-pill></div>
            @if ($d->description)<p class="mt-1 text-slate-600">{{ $d->description }}</p>@endif
            @foreach ($d->files->where('visible_to_client', true) as $f)<a href="{{ route('portal.files.show', $f) }}" target="_blank" class="mt-1 mr-3 inline-flex items-center gap-1 text-xs text-brand-600 hover:underline"><i class="bi bi-paperclip"></i>{{ $f->original_name }}</a>@endforeach
            @foreach ($d->reviews as $r)<div class="mt-2 rounded-lg border-l-4 {{ $r->decision === 'approved' ? 'border-emerald-500' : 'border-orange-500' }} bg-slate-50 px-3 py-2 text-xs"><strong>Round {{ $r->round }}: {{ $r->decision === 'approved' ? 'Approved' : 'Changes requested' }}</strong>@if ($r->comment)<div class="mt-0.5 text-slate-700">{{ $r->comment }}</div>@endif</div>@endforeach
            @if ($d->status === 'in_review')
                <div class="mt-3 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('portal.deliverables.approve', $d) }}">@csrf<button class="btn-primary btn-sm"><i class="bi bi-check2-circle"></i> Approve</button></form>
                    <button type="button" class="btn-secondary btn-sm" @click="changes = !changes" :aria-expanded="changes">Request changes</button>
                </div>
                <form x-show="changes" x-cloak method="POST" action="{{ route('portal.deliverables.changes', $d) }}" enctype="multipart/form-data" class="mt-3 space-y-2">@csrf
                    <label class="sr-only" for="c-{{ $d->id }}">What should change?</label><textarea id="c-{{ $d->id }}" name="comment" rows="3" required class="input" placeholder="What should change?"></textarea>
                    <div class="flex flex-wrap items-center justify-between gap-2"><input type="file" name="files[]" multiple class="input w-auto text-xs" aria-label="Attachments"><button class="btn-primary btn-sm">Send feedback</button></div>
                </form>
            @endif
        </div>
    @endforeach
</section>
@endif
