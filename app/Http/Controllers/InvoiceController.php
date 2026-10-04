<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OrganizationMember;
use App\Models\Payment;
use App\Models\PayoutMethod;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\InvoiceDocument;
use App\Services\InvoicePdf;
use App\Services\InvoiceStats;
use App\Support\Money;
use App\Support\TaxPlan;
use App\Support\Tenancy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private Tenancy $tenancy, private InvoiceDocument $documents) {}

    // ---------------------------------------------------------------- dashboard

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $me = $request->user();
        $isFreelancer = $this->tenancy->issuesOwnInvoices();
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $visible = Invoice::query()
            ->when($isFreelancer, fn ($q) => $q->where('user_id', $me->id))
            ->when(! $isFreelancer, fn ($q) => $q->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhere('prepared_by', $me->id)));

        $open = ['submitted', 'under_review', 'approved', 'partially_paid'];
        $invoices = (clone $visible)->with('freelancer:id,name', 'client:id,name')
            ->when($status === 'draft', fn ($q) => $q->whereIn('status', ['draft', 'rejected']))
            ->when($status === 'sent', fn ($q) => $q->whereIn('status', $open))
            ->when($status === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->when($status === 'overdue', fn ($q) => $q->whereIn('status', $open)->whereDate('due_date', '<', now()))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('number', 'like', $like)
                    ->orWhereHas('freelancer', fn ($f) => $f->where('name', 'like', $like))
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like)));
            })
            ->latest('issue_date')->latest()->paginate(25)->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'stats' => InvoiceStats::summarize((clone $visible)->get()),
            'status' => $status,
            'search' => $search,
            'inTeam' => $request->routeIs('team.*'),
        ]);
    }

    // ---------------------------------------------------------------- the three steps

    /** Step 1: who, when, what kind. */
    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);
        $isFreelancer = $this->tenancy->issuesOwnInvoices();

        $issuerId = $isFreelancer ? $request->user()->id : $request->query('freelancer');
        $issuer = $issuerId ? User::with('freelancerProfile')->find($issuerId) : null;
        if ($issuer) {
            $this->authorize('createFor', [Invoice::class, $issuer->id]);
        }

        $client = $request->filled('client') ? Client::query()->find($request->query('client')) : null;
        $project = $client && $request->filled('project') ? \App\Models\Project::query()->where('client_id', $client->id)->find($request->query('project')) : null;

        return view('invoices.wizard', $this->wizardData(new Invoice($this->defaults($issuer, $client) + ['project_id' => $project?->id]), 1, $issuer));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $data = $this->validatedDetails($request);

        $issuerId = $this->tenancy->issuesOwnInvoices() ? $request->user()->id : ($request->input('freelancer_id') ?: abort(422, 'Choose the freelancer this invoice is for.'));
        $this->authorize('createFor', [Invoice::class, $issuerId]);
        $issuer = User::with('freelancerProfile')->findOrFail($issuerId);
        $profile = $issuer->freelancerProfile;

        $attrs = $this->detailAttributes($data, $issuer);

        for ($attempt = 0; ; $attempt++) {
            [$number, $fiscalYear, $sequence] = Invoice::allocateNumber($this->tenancy->idOrFail(), $issuer->id, $profile?->country_code, $profile?->invoice_prefix ?: 'INV', $attrs['issue_date']);

            try {
                $invoice = Invoice::create($attrs + [
                    'user_id' => $issuer->id,
                    'prepared_by' => $issuer->id === $request->user()->id ? null : $request->user()->id,
                    'number' => $number, 'fiscal_year' => $fiscalYear, 'sequence' => $sequence,
                    'status' => 'draft',
                    'payout_method_id' => $this->suggestPayout($issuer, $attrs['invoice_type'], $attrs['currency'])?->id,
                ]);
                break;
            } catch (UniqueConstraintViolationException $e) {
                // Two invoices started at the same instant: take the next number.
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }

        $invoice->recalculate();

        return redirect()->route('invoices.edit', [$invoice, 'step' => 2])->with('status', 'Details saved. Add what you are billing for.');
    }

    /** Steps 1 to 3 of an existing draft. */
    public function edit(Request $request, Invoice $invoice): View
    {
        $this->authorize('update', $invoice);
        $step = min(3, max(1, (int) $request->query('step', 2)));

        return view('invoices.wizard', $this->wizardData($invoice, $step, $invoice->freelancer()->with('freelancerProfile')->first()));
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $data = $this->validatedDetails($request);
        $issuer = $invoice->freelancer()->with('freelancerProfile')->first();
        $attrs = $this->detailAttributes($data, $issuer);

        // Moving the issue date into another financial year means a new number in that year's run.
        $profile = $issuer->freelancerProfile;
        if (Invoice::fiscalYearFor($attrs['issue_date'], $profile?->country_code) !== $invoice->fiscal_year) {
            [$number, $fiscalYear, $sequence] = Invoice::allocateNumber($invoice->organization_id, $issuer->id, $profile?->country_code, $profile?->invoice_prefix ?: 'INV', $attrs['issue_date']);
            $attrs += ['number' => $number, 'fiscal_year' => $fiscalYear, 'sequence' => $sequence];
        }

        $invoice->update($attrs);
        $invoice->recalculate();

        return redirect()->route('invoices.edit', [$invoice, 'step' => 2])->with('status', 'Details saved.');
    }

    public function show(Request $request, Invoice $invoice): View|RedirectResponse
    {
        $this->authorize('view', $invoice);

        if ($request->user()->can('update', $invoice)) {
            return redirect()->route('invoices.edit', [$invoice, 'step' => 3]);
        }

        $invoice->load('freelancer:id,name,email', 'contract', 'payments.recordedBy:id,name', 'approvedBy:id,name', 'client', 'organization');

        return view('invoices.show', [
            'invoice' => $invoice,
            'payout' => \Illuminate\Support\Facades\Gate::allows('pay') || $this->tenancy->isClient() ? $this->documents->payment($invoice) : null,
            'canDuplicate' => $request->user()->can('duplicate', $invoice),
            'reports' => \App\Models\PaymentReport::query()->with('reportedBy:id,name')->where('invoice_id', $invoice->id)->latest()->get(),
        ]);
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

    // ---------------------------------------------------------------- lines, imports, payment profile, template

    public function addItem(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:250'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'unit' => ['required', Rule::in(['hours', 'items', 'fixed'])],
            'unit_rate' => ['required', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $invoice->items()->create([
            'description' => $data['description'],
            'quantity' => $data['quantity'],
            'unit' => $data['unit'],
            'unit_rate_minor' => Money::toMinor($data['unit_rate']),
            'discount_percent' => (float) ($data['discount_percent'] ?? 0),
            'position' => $invoice->items()->count(),
        ]);
        $invoice->recalculate();

        return redirect()->route('invoices.edit', [$invoice, 'step' => 2])->with('status', 'Line added.');
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

        return redirect()->route('invoices.edit', [$invoice, 'step' => 2])->with('status', 'Line removed.');
    }

    /** One line per approved time entry, so every hour can be traced and none is billed twice. */
    public function importTime(Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $entries = $this->billableEntries($invoice)->with('project:id,name', 'task:id,title')->get();

        if ($entries->isEmpty()) {
            return back()->with('error', $this->tenancy->isSolo() ? 'No unbilled billable time was found for this client or project.' : 'No approved, unbilled time is available. Time appears here once the company approves the timesheet.');
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

        return redirect()->route('invoices.edit', [$invoice, 'step' => 2])->with('status', $entries->count().' time entries added.');
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

        return redirect()->route('invoices.edit', [$invoice, 'step' => 2])->with('status', $milestones->count().' milestone(s) added.');
    }

    public function paymentProfile(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $data = $request->validate(['payout_method_id' => ['nullable', 'uuid']]);

        if (! empty($data['payout_method_id'])) {
            // Only one of the issuer's own profiles can be put on their invoice.
            PayoutMethod::where('user_id', $invoice->user_id)->findOrFail($data['payout_method_id']);
        }

        $invoice->update(['payout_method_id' => $data['payout_method_id'] ?? null]);

        return redirect()->route('invoices.edit', [$invoice, 'step' => 2])->with('status', 'Payment details updated.');
    }

    public function template(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $data = $request->validate(['template' => ['required', Rule::in(Invoice::TEMPLATES)]]);
        $invoice->update($data);

        return redirect()->route('invoices.edit', [$invoice, 'step' => 3]);
    }

    // ---------------------------------------------------------------- the document

    public function preview(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return response()->view('invoices.document', ['d' => $this->documents->build($invoice), 'pdf' => false, 'autoPrint' => $request->routeIs('invoices.print')])
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'private, no-store');
    }

    public function pdf(Invoice $invoice, InvoicePdf $pdf): Response
    {
        $this->authorize('view', $invoice);

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.InvoicePdf::filename($invoice).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** A new draft with the same parties and lines, for the next month's invoice. */
    public function duplicate(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('duplicate', $invoice);

        $issuer = $invoice->freelancer()->with('freelancerProfile')->first();
        $profile = $issuer->freelancerProfile;
        $issued = now();

        $copy = DB::transaction(function () use ($request, $invoice, $issuer, $profile, $issued) {
            [$number, $fiscalYear, $sequence] = Invoice::allocateNumber($invoice->organization_id, $issuer->id, $profile?->country_code, $profile?->invoice_prefix ?: 'INV', $issued);
            $days = max(0, (int) $invoice->issue_date->diffInDays($invoice->due_date));

            $copy = Invoice::create([
                'user_id' => $issuer->id,
                'prepared_by' => $issuer->id === $request->user()->id ? null : $request->user()->id,
                'contract_id' => $invoice->contract_id, 'client_id' => $invoice->client_id, 'bill_to_type' => $invoice->bill_to_type,
                'number' => $number, 'fiscal_year' => $fiscalYear, 'sequence' => $sequence,
                'issue_date' => $issued->toDateString(), 'due_date' => $issued->copy()->addDays($days)->toDateString(),
                'currency' => $invoice->currency, 'invoice_type' => $invoice->invoice_type, 'payment_terms' => $invoice->payment_terms, 'template' => $invoice->template,
                'tax_treatment' => $invoice->tax_treatment, 'tax_lines' => $invoice->tax_lines, 'tax_rate' => $invoice->tax_rate,
                'place_of_supply' => $invoice->place_of_supply, 'sac_code' => $invoice->sac_code, 'lut_reference' => $invoice->lut_reference,
                'exchange_rate' => $invoice->exchange_rate, 'payout_method_id' => $invoice->payout_method_id, 'notes' => $invoice->notes,
                'status' => 'draft',
            ]);

            // Time and milestone lines are not copied: those hours are already billed. Everything else is.
            foreach ($invoice->items()->whereNull('source_type')->get() as $i => $item) {
                $copy->items()->create(['description' => $item->description, 'quantity' => $item->quantity, 'unit' => $item->unit, 'unit_rate_minor' => $item->unit_rate_minor, 'position' => $i]);
            }
            $copy->recalculate();

            return $copy;
        });

        return redirect()->route('invoices.edit', [$copy, 'step' => 2])->with('status', 'Copied as a new draft. Time and milestone lines are not copied, because those are already billed.');
    }

    // ---------------------------------------------------------------- the workflow

    public function send(Request $request, Invoice $invoice, InvoicePdf $pdf): RedirectResponse
    {
        $this->authorize('submit', $invoice);
        $data = $request->validate(['email_to' => ['nullable', 'email', 'max:190']]);

        $invoice->recalculate();
        abort_if($invoice->total_minor <= 0, 422, 'The invoice total must be more than zero.');

        // From here the parties and payment details are frozen into the invoice.
        $invoice->update([
            'status' => 'submitted', 'submitted_at' => now(), 'sent_at' => now(), 'rejection_reason' => null,
            'snapshot' => $this->documents->snapshot($invoice),
        ]);
        AuditLog::record('invoice.sent', $invoice);
        $this->tellClient($invoice, 'invoice', 'New invoice '.$invoice->number, money($invoice->total_minor, $invoice->currency).' due '.$invoice->due_date->format('d M Y'), route('portal.invoice', $invoice));

        $message = 'Invoice sent for approval.';
        if (! empty($data['email_to'])) {
            $message .= $this->emailCopy($invoice->refresh(), $data['email_to'], $pdf);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', $message);
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('cancel', $invoice);
        $this->releaseSources($invoice->items);
        $invoice->update(['status' => 'void']);
        AuditLog::record('invoice.cancelled', $invoice);
        $this->tellClient($invoice, 'invoice', 'Invoice '.$invoice->number.' was cancelled', null, route('portal.invoices'));

        return back()->with('status', 'Invoice cancelled. Time and milestones on it are available again.');
    }

    public function refund(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('refund', $invoice);
        abort_unless($invoice->status === 'paid', 422, 'Only a paid invoice can be marked as refunded.');
        $invoice->update(['status' => 'refunded']);
        AuditLog::record('invoice.refunded', $invoice);
        $this->tellClient($invoice, 'payment', 'Invoice '.$invoice->number.' was refunded', money($invoice->amount_paid_minor, $invoice->currency), route('portal.invoice', $invoice));

        return back()->with('status', 'Marked as refunded. Workora records it; the money itself moves outside the app.');
    }

    /** A receipt for one payment. Clients may open their own. */
    public function receipt(Invoice $invoice, Payment $payment, InvoicePdf $pdf): Response
    {
        $this->authorize('view', $invoice);
        abort_unless($payment->invoice_id === $invoice->id && $payment->status === 'paid', 404);

        $invoice->load('freelancer.freelancerProfile', 'organization', 'client');
        $html = view('invoices.receipt', ['invoice' => $invoice, 'payment' => $payment, 'd' => $this->documents->build($invoice)])->render();

        return response($pdf->fromHtml($html), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="receipt-'.$invoice->number.'.pdf"']);
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

        // Unfreeze it so it can be corrected and sent again with current details.
        $invoice->update(['status' => 'rejected', 'rejection_reason' => $data['reason'], 'snapshot' => null]);
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

        $this->applyPayment($request, $invoice, $amount, $data);

        return back()->with('status', 'Payment recorded.');
    }

    /** The shortcut for "it has all been paid": records the whole balance as one payment. */
    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('recordPayment', $invoice);

        $data = $request->validate([
            'method' => ['required', Rule::in(['bank_transfer', 'upi', 'paypal', 'wise', 'cash', 'other'])],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $this->applyPayment($request, $invoice, $invoice->outstandingMinor(), $data);

        return back()->with('status', 'Marked as paid.');
    }

    // ---------------------------------------------------------------- internals

    /** Tell the people who log in as the invoice's client. */
    private function tellClient(Invoice $invoice, string $type, string $title, ?string $body, string $url): void
    {
        if (! $invoice->client_id) {
            return;
        }
        $client = Client::query()->find($invoice->client_id);
        if ($client) {
            app(\App\Services\Notifier::class)->send(app(\App\Services\ClientContext::class)->clientUsers($client), $type, $title, $body, $url, auth()->user());
        }
    }

    private function applyPayment(Request $request, Invoice $invoice, int $amount, array $data): void
    {
        app(\App\Services\InvoicePayments::class)->record($invoice, $amount, $data, $request->user());
    }

    private function emailCopy(Invoice $invoice, string $to, InvoicePdf $pdf): string
    {
        try {
            $bytes = $pdf->render($invoice);
            $name = $invoice->organization->name;

            Mail::raw("Hello,\n\nPlease find invoice {$invoice->number} from {$invoice->freelancer->name} attached.\nAmount: ".money($invoice->total_minor, $invoice->currency)."\nDue: {$invoice->due_date->format('d M Y')}\n\nSent through Freelancy for {$name}.\n",
                fn ($m) => $m->to($to)->subject("Invoice {$invoice->number} from {$invoice->freelancer->name}")->attachData($bytes, InvoicePdf::filename($invoice), ['mime' => 'application/pdf']));

            return ' A copy was emailed to '.$to.'.';
        } catch (\Throwable) {
            return ' The email could not be sent (check the mail settings); download the PDF and send it yourself.';
        }
    }

    private function wizardData(Invoice $invoice, int $step, ?User $issuer): array
    {
        $isFreelancer = $this->tenancy->issuesOwnInvoices();
        $data = [
            'invoice' => $invoice,
            'step' => $step,
            'issuer' => $issuer,
            'isFreelancer' => $isFreelancer,
            'freelancers' => ! $isFreelancer && ! $invoice->exists
                ? OrganizationMember::query()->with('user:id,name')->where('member_type', 'freelancer')->where('status', 'active')->get()
                : collect(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'country_code', 'default_currency']),
            'projects' => \App\Models\Project::query()->whereNotNull('client_id')->orderBy('name')->get(['id', 'name', 'client_id']),
            'contracts' => $issuer ? Contract::query()->where('user_id', $issuer->id)->where('status', 'active')->get() : collect(),
            'templates' => \App\Http\Controllers\SettingsController::TEMPLATES,
            'currencies' => array_keys(Money::CURRENCIES),
            'taxProfiles' => \App\Models\TaxProfile::query()->orderByDesc('is_default')->orderBy('name')->get()->map(fn ($t) => $t->only(['id', 'name', 'applies_to', 'treatment', 'rate', 'label', 'place_of_supply', 'sac_code', 'lut_reference']))->all(),
            'rates' => \App\Models\ExchangeRate::query()->where('to_currency', 'INR')->orderBy('effective_on')->get()->groupBy('from_currency')->map(fn ($g) => ['rate' => rtrim(rtrim(number_format($g->last()->rate, 6, '.', ''), '0'), '.'), 'on' => $g->last()->effective_on->format('d M Y')])->all(),
            'taxRate' => $invoice->exists ? TaxPlan::totalRate($invoice->tax_lines) : (float) ($invoice->tax_rate ?? 0),
            'taxLabel' => collect($invoice->tax_lines ?? [])->first()['label'] ?? '',
        ];

        if ($step >= 2 && $invoice->exists) {
            $invoice->load('items', 'payoutMethod', 'contract');
            $data['profiles'] = PayoutMethod::where('user_id', $invoice->user_id)->orderByDesc('is_default')->get();
            $data['availableMinutes'] = (int) $this->billableEntries($invoice)->sum('minutes');
            $data['availableMilestones'] = $invoice->contract ? $invoice->contract->milestones()->where('status', 'approved')->get() : collect();
        }

        if ($step === 3 && $invoice->exists) {
            $data['warnings'] = $this->documents->warnings($invoice);
        }

        return $data;
    }

    /** Sensible starting values; everything can be changed on the form. */
    private function defaults(?User $issuer, ?Client $client): array
    {
        $org = $this->tenancy->organization();
        $profile = $issuer?->freelancerProfile;
        $supplierCountry = $profile?->country_code;
        $billCountry = $client?->country_code ?: $org->country_code;

        $international = $supplierCountry && $billCountry && $supplierCountry !== $billCountry;
        $currency = $international ? ($client?->default_currency ?: $profile?->default_currency ?: 'USD') : ($client?->default_currency ?: $org->base_currency);
        $terms = (int) $org->setting('payment_terms_days', 14);

        $treatment = 'none';
        if ($supplierCountry === 'IN') {
            $treatment = $international ? 'export_lut' : (! empty($profile?->tax_ids['gstin']) ? 'gst_intra' : 'none');
        }

        return [
            'bill_to_type' => $client || $org->mode === 'solo' ? 'client' : 'company',
            'client_id' => $client?->id,
            'issue_date' => now(),
            'due_date' => now()->addDays($terms),
            'payment_terms' => Invoice::TERMS[$terms] ?? 'Net '.$terms,
            'currency' => $currency,
            'invoice_type' => $international ? 'international' : 'domestic',
            'template' => $org->setting('invoice_template', 'professional'),
            'tax_treatment' => $treatment,
            'tax_rate' => in_array($treatment, ['gst_intra', 'gst_inter'], true) ? (float) $org->default_tax_rate : 0,
            'notes' => $org->setting('invoice_notes'),
        ];
    }

    private function validatedDetails(Request $request): array
    {
        $request->merge(['sac_code' => strtoupper(trim((string) $request->input('sac_code')))]);

        return $request->validate([
            'freelancer_id' => ['nullable', 'uuid'],
            'bill_to_type' => ['required', Rule::in($this->tenancy->isSolo() ? ['client'] : ['company', 'client'])],
            'client_id' => ['required_if:bill_to_type,client', 'nullable', 'uuid'],
            'contract_id' => ['nullable', 'uuid'],
            'project_id' => ['nullable', 'uuid'],
            'issue_date' => ['required', 'date'],
            'terms_days' => ['required', Rule::in([...array_map('strval', array_keys(Invoice::TERMS)), 'custom'])],
            'due_date' => ['required_if:terms_days,custom', 'nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', Rule::in(array_keys(Money::CURRENCIES))],
            'invoice_type' => ['required', Rule::in(['domestic', 'international'])],
            'service_period_start' => ['nullable', 'date'],
            'service_period_end' => ['nullable', 'date', 'after_or_equal:service_period_start'],
            'template' => ['required', Rule::in(Invoice::TEMPLATES)],
            'tax_treatment' => ['required', Rule::in(array_keys(Invoice::TREATMENTS))],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_label' => ['nullable', 'string', 'max:30'],
            'place_of_supply' => ['nullable', 'string', 'max:80'],
            'sac_code' => ['nullable', 'regex:/^[0-9A-Z]{0,20}$/'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
            'lut_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** Turn the form into invoice columns: dates, terms, bill-to, tax lines. */
    private function detailAttributes(array $data, User $issuer): array
    {
        $issued = \Illuminate\Support\Carbon::parse($data['issue_date'])->startOfDay();

        if ($data['terms_days'] === 'custom') {
            $due = \Illuminate\Support\Carbon::parse($data['due_date'])->startOfDay();
            $terms = 'Due '.$due->format('d M Y');
        } else {
            $days = (int) $data['terms_days'];
            $due = $issued->copy()->addDays($days);
            $terms = Invoice::TERMS[$days];
        }

        if ($data['bill_to_type'] === 'client') {
            Client::findOrFail($data['client_id']);
        }
        if (! empty($data['project_id'])) {
            \App\Models\Project::query()->where('client_id', $data['bill_to_type'] === 'client' ? $data['client_id'] : null)->findOrFail($data['project_id']);
        }
        if (! empty($data['contract_id'])) {
            Contract::query()->where('user_id', $issuer->id)->where('status', 'active')->findOrFail($data['contract_id']);
        }

        $lines = TaxPlan::lines($data['tax_treatment'], (float) ($data['tax_rate'] ?? 0), $data['tax_label'] ?? null);
        $international = $data['invoice_type'] === 'international';

        return [
            'bill_to_type' => $data['bill_to_type'],
            'client_id' => $data['bill_to_type'] === 'client' ? $data['client_id'] : null,
            'contract_id' => $data['contract_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'issue_date' => $issued,
            'due_date' => $due,
            'payment_terms' => $terms,
            'currency' => $data['currency'],
            'invoice_type' => $data['invoice_type'],
            'service_period_start' => $data['service_period_start'] ?? null,
            'service_period_end' => $data['service_period_end'] ?? null,
            'template' => $data['template'],
            'tax_treatment' => $data['tax_treatment'],
            'tax_lines' => $lines ?: null,
            'tax_rate' => TaxPlan::totalRate($lines),
            'place_of_supply' => ! $international ? ($data['place_of_supply'] ?? null) : null,
            'sac_code' => $data['sac_code'] ?: null,
            'exchange_rate' => $international ? ($data['exchange_rate'] ?? null) : null,
            'lut_reference' => $international ? ($data['lut_reference'] ?? null) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** Which saved payment profile fits: India for domestic, the matching currency for international. */
    private function suggestPayout(User $issuer, string $type, string $currency): ?PayoutMethod
    {
        $mine = PayoutMethod::where('user_id', $issuer->id)->get();

        if ($type === 'domestic') {
            return $mine->where('kind', 'domestic')->sortByDesc('is_default')->first() ?? $mine->sortByDesc('is_default')->first();
        }

        $international = $mine->where('kind', 'international');

        return $international->where('currency', $currency)->sortByDesc('is_default')->first()
            ?? $international->sortByDesc('is_default')->first();
    }

    /** Unbilled, approved, billable time for this invoice's freelancer (and project, if the contract names one). */
    private function billableEntries(Invoice $invoice)
    {
        $solo = $this->tenancy->isSolo();

        return TimeEntry::query()->where('user_id', $invoice->user_id)->where('is_billable', true)->whereNull('locked_at')->where('minutes', '>', 0)
            // With a company, only approved timesheets are billable. On a solo workspace nobody approves hours.
            ->when(! $solo, fn ($q) => $q->whereHas('timesheet', fn ($t) => $t->where('status', 'approved')))
            ->when($solo && $invoice->project_id, fn ($q) => $q->where('project_id', $invoice->project_id))
            ->when($solo && ! $invoice->project_id && $invoice->client_id, fn ($q) => $q->where('client_id', $invoice->client_id))
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
