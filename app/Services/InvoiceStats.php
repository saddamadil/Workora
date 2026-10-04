<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Collection;

/** Totals for the invoice dashboard, kept per currency because one currency cannot be added to another. */
class InvoiceStats
{
    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return array{invoiced: array<string, int>, paid: array<string, int>, outstanding: array<string, int>, overdue: array<string, int>}
     */
    public static function summarize(Collection $invoices): array
    {
        $sent = $invoices->whereNotIn('status', ['draft', 'rejected', 'void', 'refunded']);
        $by = fn (Collection $rows, callable $fn) => $rows->groupBy('currency')->map(fn ($g) => (int) $g->sum($fn))->filter()->all();

        return [
            'invoiced' => $by($sent, fn (Invoice $i) => $i->total_minor),
            'paid' => $by($sent, fn (Invoice $i) => $i->amount_paid_minor),
            'outstanding' => $by($sent->whereIn('status', ['submitted', 'under_review', 'approved', 'partially_paid']), fn (Invoice $i) => $i->outstandingMinor()),
            'overdue' => $by($sent->filter(fn (Invoice $i) => $i->displayStatus() === 'overdue'), fn (Invoice $i) => $i->outstandingMinor()),
        ];
    }
}
