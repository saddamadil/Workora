<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\Countries;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Raised by the freelancer, approved by the company. Once approved it is a
 * financial record: corrections happen through a new invoice or a void, never
 * by editing the original.
 */
class Invoice extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    /** What kind of tax line(s) an invoice carries. Rates are always entered by the issuer. */
    public const TREATMENTS = [
        'none' => 'No tax charged',
        'gst_intra' => 'GST: CGST + SGST (same state)',
        'gst_inter' => 'GST: IGST (different state)',
        'export_lut' => 'Export of services: zero-rated under LUT',
        'export_igst' => 'Export of services: IGST charged',
        'vat' => 'VAT / sales tax',
        'custom' => 'Other tax',
    ];

    public const TERMS = [
        0 => 'Due on receipt', 7 => 'Net 7', 14 => 'Net 14', 15 => 'Net 15', 30 => 'Net 30', 45 => 'Net 45', 60 => 'Net 60',
    ];

    public const TEMPLATES = ['professional', 'modern', 'minimal', 'international', 'gst'];

    protected $fillable = [
        'organization_id', 'user_id', 'contract_id', 'number', 'issue_date', 'due_date',
        'currency', 'subtotal_minor', 'tax_rate', 'tax_minor', 'total_minor',
        'invoice_type', 'bill_to_type', 'client_id', 'prepared_by', 'payout_method_id',
        'service_period_start', 'service_period_end', 'payment_terms', 'template',
        'tax_treatment', 'tax_lines', 'place_of_supply', 'sac_code',
        'exchange_rate', 'inr_equivalent_minor', 'lut_reference', 'fiscal_year', 'sequence', 'sent_at', 'snapshot',
        'amount_paid_minor', 'status', 'notes', 'pdf_path',
        'submitted_at', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'service_period_start' => 'date',
            'service_period_end' => 'date',
            'tax_lines' => 'array',
            'snapshot' => 'array',
            'exchange_rate' => 'decimal:6',
            'inr_equivalent_minor' => 'integer',
            'sent_at' => 'datetime',
            'subtotal_minor' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'amount_paid_minor' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'attachable');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function isOverdue(): bool
    {
        return $this->due_date->isPast()
            && ! in_array($this->status, ['paid', 'void'], true);
    }

    public function outstandingMinor(): int
    {
        return max(0, $this->total_minor - $this->amount_paid_minor);
    }

    /** Recompute subtotal, tax and total from the line items. */
    /** The tax components to apply, each with a label and a percentage. */
    public function taxLinesList(): array
    {
        if (! empty($this->tax_lines)) {
            return array_values(array_filter($this->tax_lines, fn ($l) => isset($l['label'], $l['rate'])));
        }

        return (float) $this->tax_rate > 0 ? [['label' => 'Tax', 'rate' => (float) $this->tax_rate]] : [];
    }

    /** Subtotal, each tax component, total, and the INR value of an export invoice, all from the lines. */
    public function recalculate(): void
    {
        $this->subtotal_minor = (int) $this->items()->sum('amount_minor');

        $lines = $this->taxLinesList();
        $this->tax_minor = (int) array_sum(array_map(fn ($l) => round($this->subtotal_minor * ((float) $l['rate'] / 100)), $lines));
        $this->tax_rate = array_sum(array_column($lines, 'rate'));
        $this->total_minor = $this->subtotal_minor + $this->tax_minor;

        $this->inr_equivalent_minor = $this->isInternational() && $this->currency !== 'INR' && (float) $this->exchange_rate > 0
            ? (int) round($this->total_minor * (float) $this->exchange_rate)
            : null;

        $this->save();
    }

    /** Minor units of tax for each component, for showing "CGST 9% ₹x, SGST 9% ₹x" on the document. */
    public function taxBreakdown(): array
    {
        return array_map(fn ($l) => $l + ['amount_minor' => (int) round($this->subtotal_minor * ((float) $l['rate'] / 100))], $this->taxLinesList());
    }

    public function isInternational(): bool
    {
        return $this->invoice_type === 'international';
    }

    /** What the dashboard calls it: Draft, Sent, Paid or Overdue (or the finer status). */
    public function displayStatus(): string
    {
        if ($this->status === 'draft' || $this->status === 'rejected') {
            return $this->status === 'draft' ? 'draft' : 'rejected';
        }

        if ($this->status === 'paid') {
            return 'paid';
        }

        if ($this->status === 'void') {
            return 'void';
        }

        return $this->isOverdue() && in_array($this->status, ['approved', 'partially_paid', 'submitted', 'under_review'], true) ? 'overdue' : 'sent';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(PayoutMethod::class);
    }

    /**
     * Indian financial years run April to March ("2026-27"); everywhere else it is the
     * calendar year ("2026"). Numbering restarts with each one.
     */
    public static function fiscalYearFor(Carbon $date, ?string $country): string
    {
        if (! Countries::hasAprilFinancialYear($country)) {
            return (string) $date->year;
        }

        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return $start.'-'.substr((string) ($start + 1), -2);
    }

    /**
     * The next number for this freelancer: PREFIX-FINANCIALYEAR-001, consecutive within that
     * year and unique across everything they have issued (the shape tax rules ask for).
     *
     * @return array{0: string, 1: string, 2: int} number, fiscal year, sequence
     */
    public static function allocateNumber(string $organizationId, string $userId, ?string $country, string $prefix = 'INV', ?Carbon $date = null): array
    {
        $fiscalYear = static::fiscalYearFor($date ?? now(), $country);
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix)) ?: 'INV';

        $issued = static::withoutGlobalScopes()->where('organization_id', $organizationId)->where('user_id', $userId);
        $sequence = (int) (clone $issued)->where('fiscal_year', $fiscalYear)->max('sequence') + 1;

        do {
            $number = sprintf('%s-%s-%03d', $prefix, $fiscalYear, $sequence++);
        } while ((clone $issued)->where('number', $number)->exists());

        return [$number, $fiscalYear, $sequence - 1];
    }
}
