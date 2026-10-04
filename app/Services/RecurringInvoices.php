<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PayoutMethod;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

class RecurringInvoices
{
    public function __construct(private Tenancy $tenancy, private InvoiceSender $sender) {}

    /** Capture an existing invoice as a schedule. Time and milestone lines are not repeated: those are one-off. */
    public function fromInvoice(Invoice $invoice, string $frequency, Carbon $start, ?Carbon $ends, bool $autoSend, string $title): RecurringInvoice
    {
        $invoice->loadMissing('items');
        $issued = $invoice->issue_date->startOfDay();
        $termDays = max(0, (int) $issued->diffInDays($invoice->due_date->startOfDay()));

        return RecurringInvoice::create([
            'user_id' => $invoice->user_id, 'client_id' => $invoice->client_id, 'project_id' => $invoice->project_id, 'title' => $title,
            'frequency' => $frequency, 'next_run_on' => $start->toDateString(), 'ends_on' => $ends?->toDateString(), 'auto_send' => $autoSend, 'status' => 'active',
            'details' => [
                'currency' => $invoice->currency, 'invoice_type' => $invoice->invoice_type, 'template' => $invoice->template, 'payment_terms' => $invoice->payment_terms, 'terms_days' => $termDays,
                'tax_treatment' => $invoice->tax_treatment, 'tax_lines' => $invoice->tax_lines, 'tax_rate' => $invoice->tax_rate, 'place_of_supply' => $invoice->place_of_supply,
                'sac_code' => $invoice->sac_code, 'lut_reference' => $invoice->lut_reference, 'exchange_rate' => $invoice->exchange_rate, 'notes' => $invoice->notes, 'payout_method_id' => $invoice->payout_method_id,
            ],
            'items' => $invoice->items->whereNull('source_type')->map(fn ($i) => ['description' => $i->description, 'quantity' => (float) $i->quantity, 'unit' => $i->unit, 'unit_rate_minor' => $i->unit_rate_minor, 'discount_percent' => (float) $i->discount_percent])->values()->all(),
        ]);
    }

    /** Make one invoice from the schedule and move it to its next date. Returns the new invoice. */
    public function run(RecurringInvoice $r, ?Carbon $today = null): Invoice
    {
        $today ??= now()->startOfDay();
        $d = $r->details;
        $issuer = User::query()->with('freelancerProfile')->findOrFail($r->user_id);
        $profile = $issuer->freelancerProfile;
        $issue = $r->next_run_on->copy()->startOfDay();

        for ($attempt = 0; ; $attempt++) {
            [$number, $fy, $seq] = Invoice::allocateNumber($r->organization_id, $issuer->id, $profile?->country_code, $profile?->invoice_prefix ?: 'INV', $issue);
            try {
                $invoice = Invoice::create([
                    'user_id' => $issuer->id, 'client_id' => $r->client_id, 'project_id' => $r->project_id, 'bill_to_type' => 'client',
                    'number' => $number, 'fiscal_year' => $fy, 'sequence' => $seq, 'status' => 'draft',
                    'issue_date' => $issue->toDateString(), 'due_date' => $issue->copy()->addDays((int) ($d['terms_days'] ?? 14))->toDateString(),
                    'currency' => $d['currency'], 'invoice_type' => $d['invoice_type'], 'template' => $d['template'], 'payment_terms' => $d['payment_terms'] ?? null,
                    'tax_treatment' => $d['tax_treatment'], 'tax_lines' => $d['tax_lines'], 'tax_rate' => $d['tax_rate'] ?? 0, 'place_of_supply' => $d['place_of_supply'] ?? null,
                    'sac_code' => $d['sac_code'] ?? null, 'lut_reference' => $d['lut_reference'] ?? null, 'exchange_rate' => $d['exchange_rate'] ?? null, 'notes' => $d['notes'] ?? null,
                    'payout_method_id' => PayoutMethod::query()->whereKey($d['payout_method_id'] ?? null)->value('id'),
                ]);
                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }

        foreach ($r->items as $i => $item) {
            $invoice->items()->create($item + ['position' => $i]);
        }
        $invoice->recalculate();

        if ($r->auto_send && $invoice->total_minor > 0) {
            $this->sender->send($invoice, null);
        }

        $next = $r->advance($r->next_run_on);
        // Catch up without piling up: if the schedule was off for a while, the next one is in the future.
        while ($next->lt($today) || $next->eq($today)) {
            $next = $r->advance($next);
        }
        $ended = $r->ends_on && $next->gt($r->ends_on);
        $r->update(['next_run_on' => $next->toDateString(), 'last_run_on' => $issue->toDateString(), 'runs_count' => $r->runs_count + 1, 'status' => $ended ? 'ended' : $r->status]);

        return $invoice;
    }
}
