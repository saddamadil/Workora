<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\TimeEntry;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private Tenancy $tenancy) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);
        $status = $request->query('status');

        $invoices = Invoice::query()->with('freelancer:id,name')
            ->when($this->tenancy->isFreelancer(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['submitted', 'under_review']))
            ->when($status === 'unpaid', fn ($q) => $q->whereIn('status', ['approved', 'partially_paid']))
            ->when($status && ! in_array($status, ['open', 'unpaid'], true), fn ($q) => $q->where('status', $status))
            ->latest('issue_date')->latest()->paginate(30)->withQueryString();

        return view('invoices.index', ['invoices' => $invoices, 'status' => $status]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);

        return view('invoices.create', [
            'contracts' => Contract::query()->where('user_id', $request->user()->id)->where('status', 'active')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $data = $request->validate(['contract_id' => ['required', 'uuid']]);
        $contract = Contract::query()->where('user_id', $request->user()->id)->where('status', 'active')->findOrFail($data['contract_id']);

        $invoice = Invoice::create([
            'user_id' => $request->user()->id,
            'contract_id' => $contract->id,
            'number' => Invoice::nextNumber($this->tenancy->idOrFail(), $request->user()->id),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays($contract->payment_terms_days)->toDateString(),
            'currency' => $contract->currency,
            'tax_rate' => $this->tenancy->organization()->default_tax_rate ?? 0,
            'status' => 'draft',
        ]);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft created. Add what you worked on.');
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load('freelancer:id,name,email', 'contract', 'items', 'payments.recordedBy:id,name', 'approvedBy:id,name');
        $contract = $invoice->contract;

        // What could still be added to this draft.
        $availableMinutes = 0;
        $availableMilestones = collect();
        if ($invoice->isEditable() && $contract) {
            $availableMinutes = (int) $this->billableEntries($invoice)->sum('minutes');
            $availableMilestones = $contract->milestones()->where('status', 'approved')->get();
        }

        return view('invoices.show', [
            'invoice' => $invoice,
            'availableMinutes' => $availableMinutes,
            'availableMilestones' => $availableMilestones,
            'canEdit' => request()->user()->can('update', $invoice),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $data = $request->validate([
            'due_date' => ['required', 'date', 'after_or_equal:'.$invoice->issue_date->toDateString()],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice->update($data);
        $invoice->recalculate();

        return back()->with('status', 'Saved.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        DB::transaction(function () use ($invoice) {
            $this->releaseSources($invoice->items);
            $invoice->items()->delete();
            $invoice->delete();
        });

        return redirect()->route('invoices.index')->with('status', 'Draft deleted.');
    }

    public function addItem(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:250'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'unit' => ['required', Rule::in(['hours', 'items', 'fixed'])],
            'unit_rate' => ['required', 'numeric', 'min:0'],
        ]);

        $invoice->items()->create([
            'description' => $data['description'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'unit_rate_minor' => Money::toMinor($data['unit_rate']),
            'position' => $invoice->items()->count(),
        ]);
        $invoice->recalculate();

        return back()->with('status', 'Line added.');
    }

    public function removeItem(Invoice $invoice, InvoiceItem $item): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_unless($item->invoice_id === $invoice->id, 404);

        DB::transaction(function () use ($invoice, $item) {
            $this->releaseSources(collect([$item]));
            $item->delete();
            $invoice->recalculate();
        });

        return back()->with('status', 'Line removed.');
    }

    /** One line per approved time entry, so every hour can be traced and none is billed twice. */
    public function importTime(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $entries = $this->billableEntries($invoice)->with('project:id,name', 'task:id,title')->get();

        if ($entries->isEmpty()) {
            return back()->with('error', 'No approved, unbilled time is available. Time appears here once the company approves your timesheet.');
        }

        DB::transaction(function () use ($invoice, $entries) {
            $position = $invoice->items()->count();

            foreach ($entries as $entry) {
                $invoice->items()->create([
                    'description' => $entry->entry_date->format('d M').' · '.$entry->project->name.($entry->task ? ' · '.$entry->task->title : '').($entry->description ? ' · '.$entry->description : ''),
                    'quantity' => $entry->billableHours(),
                    'unit' => 'hours',
                    'unit_rate_minor' => (int) ($entry->rate_minor ?? 0),
                    'source_type' => TimeEntry::class,
                    'source_id' => $entry->id,
                    'position' => $position++,
                ]);
            }

            // Lock with a plain query: the model refuses edits to locked rows, which is the point.
            TimeEntry::whereKey($entries->pluck('id'))->update(['locked_at' => now()]);
            $invoice->recalculate();
        });

        return back()->with('status', $entries->count().' time entries added.');
    }

    public function importMilestones(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $milestones = $invoice->contract?->milestones()->where('status', 'approved')->get() ?? collect();

        if ($milestones->isEmpty()) {
            return back()->with('error', 'No approved milestones are waiting to be invoiced.');
        }

        DB::transaction(function () use ($invoice, $milestones) {
            $position = $invoice->items()->count();

            foreach ($milestones as $m) {
                $invoice->items()->create([
                    'description' => 'Milestone: '.$m->title,
                    'quantity' => 1,
                    'unit' => 'fixed',
                    'unit_rate_minor' => $m->amount_minor,
                    'source_type' => ContractMilestone::class,
                    'source_id' => $m->id,
                    'position' => $position++,
                ]);
                $m->update(['status' => 'invoiced']);
            }
            $invoice->recalculate();
        });

        return back()->with('status', $milestones->count().' milestone(s) added.');
    }

    public function submit(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('submit', $invoice);
        abort_if($invoice->items()->sum('amount_minor') <= 0, 422, 'The invoice total must be more than zero.');

        $invoice->recalculate();
        $invoice->update(['status' => 'submitted', 'submitted_at' => now(), 'rejection_reason' => null]);
        AuditLog::record('invoice.submitted', $invoice);

        return back()->with('status', 'Invoice sent for approval.');
    }

    public function approve(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('approve', $invoice);

        $invoice->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        AuditLog::record('invoice.approved', $invoice);

        return back()->with('status', 'Invoice approved. Record the payment once it is made.');
    }

    public function reject(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('reject', $invoice);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $invoice->update(['status' => 'rejected', 'rejection_reason' => $data['reason']]);
        AuditLog::record('invoice.rejected', $invoice);

        return back()->with('status', 'Sent back to the freelancer.');
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('recordPayment', $invoice);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['bank_transfer', 'upi', 'paypal', 'wise', 'cash', 'other'])],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = Money::toMinor($data['amount']);

        if ($amount > $invoice->outstandingMinor()) {
            return back()->withInput()->withErrors(['amount' => 'That is more than is still owed ('.money($invoice->outstandingMinor(), $invoice->currency).').']);
        }

        DB::transaction(function () use ($request, $invoice, $data, $amount) {
            // Lock the row so two people recording the same payment cannot both succeed.
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'amount_minor' => $amount,
                'currency' => $invoice->currency,
                'method' => $data['method'],
                'status' => 'paid',
                'paid_at' => $data['paid_on'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            $paid = $invoice->amount_paid_minor + $amount;
            $invoice->update(['amount_paid_minor' => $paid, 'status' => $paid >= $invoice->total_minor ? 'paid' : 'partially_paid']);
            AuditLog::record('payment.recorded', $invoice, ['new' => ['amount_minor' => $amount]]);
        });

        return back()->with('status', 'Payment recorded.');
    }

    /** Unbilled, approved, billable time for this invoice's freelancer (and project, if the contract names one). */
    private function billableEntries(Invoice $invoice)
    {
        return TimeEntry::query()->where('user_id', $invoice->user_id)->where('is_billable', true)->whereNull('locked_at')->where('minutes', '>', 0)
            ->whereHas('timesheet', fn ($q) => $q->where('status', 'approved'))
            ->when($invoice->contract?->project_id, fn ($q, $p) => $q->where('project_id', $p))
            ->orderBy('entry_date');
    }

    /** Put time and milestones back in the pool when their line is removed. */
    private function releaseSources($items): void
    {
        $timeIds = $items->where('source_type', TimeEntry::class)->pluck('source_id');
        if ($timeIds->isNotEmpty()) {
            TimeEntry::whereKey($timeIds)->update(['locked_at' => null]);
        }

        $milestoneIds = $items->where('source_type', ContractMilestone::class)->pluck('source_id');
        if ($milestoneIds->isNotEmpty()) {
            ContractMilestone::whereKey($milestoneIds)->update(['status' => 'approved']);
        }
    }
}
