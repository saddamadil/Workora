<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
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

    protected $fillable = [
        'organization_id', 'user_id', 'contract_id', 'number', 'issue_date', 'due_date',
        'currency', 'subtotal_minor', 'tax_rate', 'tax_minor', 'total_minor',
        'amount_paid_minor', 'status', 'notes', 'pdf_path',
        'submitted_at', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
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
    public function recalculate(): void
    {
        $this->subtotal_minor = (int) $this->items()->sum('amount_minor');
        $this->tax_minor = (int) round($this->subtotal_minor * ((float) $this->tax_rate / 100));
        $this->total_minor = $this->subtotal_minor + $this->tax_minor;

        $this->save();
    }

    /** Next invoice number for a freelancer within one company. */
    public static function nextNumber(string $organizationId, string $userId): string
    {
        $year = now()->year;

        $count = static::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('user_id', $userId)
            ->whereYear('issue_date', $year)
            ->count();

        return sprintf('INV-%d-%04d', $year, $count + 1);
    }
}
