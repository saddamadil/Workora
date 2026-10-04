<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Services\RecurringInvoices;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Schedules that issue an invoice again every week, month, quarter or year. */
class RecurringInvoiceController extends Controller
{
    public function __construct(private Tenancy $tenancy, private RecurringInvoices $service) {}

    private function guard(Request $request): void
    {
        abort_unless($this->tenancy->issuesOwnInvoices() || $this->tenancy->role()?->canApprovePayment() || $this->tenancy->role()?->seesMoney(), 403);
    }

    public function index(Request $request): View
    {
        $this->guard($request);
        $schedules = RecurringInvoice::query()->with('client')->orderByRaw("status = 'active' desc")->orderBy('next_run_on')->get();
        $totals = [];
        foreach ($schedules as $r) {
            $minor = 0;
            foreach ($r->items as $i) {
                $minor += (int) round($i['quantity'] * $i['unit_rate_minor'] * (1 - ($i['discount_percent'] ?? 0) / 100));
            }
            $totals[$r->id] = Money::format($minor, $r->details['currency']).' + tax';
        }

        return view('invoices.recurring', ['schedules' => $schedules, 'totals' => $totals, 'frequencies' => RecurringInvoice::FREQUENCIES]);
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->guard($request);
        $this->authorize('duplicate', $invoice);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'frequency' => ['required', Rule::in(array_keys(RecurringInvoice::FREQUENCIES))],
            'start_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['nullable', 'date', 'after:start_on'],
            'auto_send' => ['nullable', 'boolean'],
        ]);
        abort_if($invoice->client_id === null, 422, 'Only invoices addressed to a client can repeat.');
        $this->service->fromInvoice($invoice, $data['frequency'], Carbon::parse($data['start_on']), isset($data['ends_on']) ? Carbon::parse($data['ends_on']) : null, $request->boolean('auto_send'), ($data['title'] ?? null) ?: 'Recurring '.$invoice->number);

        return redirect()->route('recurring.index')->with('status', 'Schedule created. The first invoice is made on '.Carbon::parse($data['start_on'])->format('d M Y').'.');
    }

    public function toggle(Request $request, RecurringInvoice $recurring): RedirectResponse
    {
        $this->guard($request);
        abort_if($recurring->status === 'ended', 422);
        $recurring->update(['status' => $recurring->status === 'active' ? 'paused' : 'active']);

        return back()->with('status', $recurring->status === 'active' ? 'Schedule resumed.' : 'Schedule paused.');
    }

    public function run(Request $request, RecurringInvoice $recurring): RedirectResponse
    {
        $this->guard($request);
        $invoice = $this->service->run($recurring);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice '.$invoice->number.' created from the schedule.');
    }

    public function destroy(Request $request, RecurringInvoice $recurring): RedirectResponse
    {
        $this->guard($request);
        $recurring->delete();

        return back()->with('status', 'Schedule deleted. Invoices already made are kept.');
    }
}
