<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Records money received against an invoice, one place for every path that does it. */
class InvoicePayments
{
    public function __construct(private Notifier $notifier, private ClientContext $ctx) {}

    /** @param  array{method: string, paid_on: string, reference?: ?string, notes?: ?string}  $data */
    public function record(Invoice $invoice, int $amount, array $data, User $by): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $amount, $by) {
            // Lock the row so two people recording the same payment cannot both succeed.
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            abort_if($amount <= 0 || $amount > $invoice->outstandingMinor(), 422, 'Nothing is owed on this invoice.');

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'amount_minor' => $amount,
                'currency' => $invoice->currency,
                'method' => $data['method'],
                'status' => 'paid',
                'paid_at' => $data['paid_on'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $by->id,
            ]);

            $paid = $invoice->amount_paid_minor + $amount;
            $invoice->update(['amount_paid_minor' => $paid, 'status' => $paid >= $invoice->total_minor ? 'paid' : 'partially_paid']);
            AuditLog::record('payment.recorded', $invoice, ['new' => ['amount_minor' => $amount]]);

            if ($invoice->client_id && ($client = Client::query()->find($invoice->client_id))) {
                $this->notifier->send($this->ctx->clientUsers($client), 'payment', 'Payment received for '.$invoice->number, money($amount, $invoice->currency), route('portal.invoice', $invoice), $by);
            }

            return $payment;
        });
    }
}
