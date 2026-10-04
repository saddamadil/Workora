<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;

/** The step that turns a draft into a sent invoice: freeze the details and tell the client. */
class InvoiceSender
{
    public function __construct(private InvoiceDocument $documents, private Notifier $notifier, private ClientContext $ctx) {}

    public function send(Invoice $invoice, ?User $by = null): void
    {
        $invoice->recalculate();
        abort_if($invoice->total_minor <= 0, 422, 'The invoice total must be more than zero.');

        $invoice->update([
            'status' => 'submitted', 'submitted_at' => now(), 'sent_at' => now(), 'rejection_reason' => null,
            'snapshot' => $this->documents->snapshot($invoice),
        ]);
        AuditLog::record('invoice.sent', $invoice);

        if ($invoice->client_id && ($client = Client::query()->find($invoice->client_id))) {
            $this->notifier->send($this->ctx->clientUsers($client), 'invoice', 'New invoice '.$invoice->number, money($invoice->total_minor, $invoice->currency).' due '.$invoice->due_date->format('d M Y'), route('portal.invoice', $invoice), $by);
        }
    }
}
