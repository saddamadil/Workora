<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\PaymentReport;
use App\Services\ClientContext;
use App\Services\InvoicePayments;
use App\Services\Notifier;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** "I have paid": a client tells the freelancer a transfer was made. Nothing changes until it is confirmed. */
class PaymentReportController extends Controller
{
    public function __construct(private Tenancy $tenancy, private ClientContext $ctx) {}

    public function store(Request $request, Invoice $invoice, Notifier $notifier): RedirectResponse
    {
        abort_unless($this->ctx->isPortal(), 403);
        $this->authorize('view', $invoice);
        abort_if($invoice->outstandingMinor() <= 0, 422, 'Nothing is owed on this invoice.');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['bank_transfer', 'upi', 'paypal', 'wise', 'cash', 'other'])],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['required', 'string', 'max:120'],
        ], ['reference.required' => 'Add the transaction reference so it can be matched.']);

        $amount = Money::toMinor($data['amount']);
        if ($amount > $invoice->outstandingMinor()) {
            return back()->withInput()->withErrors(['amount' => 'That is more than is still due ('.money($invoice->outstandingMinor(), $invoice->currency).').']);
        }

        $report = PaymentReport::create(['invoice_id' => $invoice->id, 'reported_by' => $request->user()->id, 'amount_minor' => $amount, 'method' => $data['method'], 'reference' => $data['reference'], 'paid_on' => $data['paid_on'], 'status' => 'pending']);
        AuditLog::record('payment.reported', $invoice, ['new' => ['amount_minor' => $amount, 'reference' => $data['reference']]]);
        $notifier->send($this->ctx->staffToNotify(), 'payment', $request->user()->name.' says they paid '.$invoice->number, money($amount, $invoice->currency).' · ref '.$data['reference'], route('invoices.show', $invoice), $request->user());

        return back()->with('status', 'Thank you. Your freelancer will confirm the payment once it arrives.');
    }

    public function confirm(Request $request, PaymentReport $report, InvoicePayments $payments, Notifier $notifier): RedirectResponse
    {
        $invoice = Invoice::query()->findOrFail($report->invoice_id);
        $this->authorize('recordPayment', $invoice);
        abort_unless($report->status === 'pending', 422, 'This report was already handled.');

        $amount = min($report->amount_minor, $invoice->outstandingMinor());
        $payments->record($invoice, $amount, ['method' => $report->method, 'paid_on' => $report->paid_on->toDateString(), 'reference' => $report->reference, 'notes' => 'Reported by the client'], $request->user());
        $report->update(['status' => 'confirmed']);

        return back()->with('status', 'Payment confirmed and recorded.');
    }

    public function reject(Request $request, PaymentReport $report, Notifier $notifier): RedirectResponse
    {
        $invoice = Invoice::query()->findOrFail($report->invoice_id);
        $this->authorize('recordPayment', $invoice);
        abort_unless($report->status === 'pending', 422, 'This report was already handled.');
        $report->update(['status' => 'rejected']);

        $client = \App\Models\Client::query()->find($invoice->client_id);
        if ($client) {
            $notifier->send($this->ctx->clientUsers($client), 'payment', 'Payment not found for '.$invoice->number, 'Your freelancer could not match the payment you reported. Please check the reference or message them.', route('portal.invoice', $invoice), $request->user());
        }

        return back()->with('status', 'Marked as not received. The client has been told.');
    }
}
