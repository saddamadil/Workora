<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToOrganization, HasUuids;

    public const CATEGORIES = ['Software', 'Travel', 'Equipment', 'Hosting', 'Fees', 'Marketing', 'Office', 'Meals', 'Other'];

    protected $fillable = ['organization_id', 'user_id', 'client_id', 'project_id', 'spent_on', 'category', 'description', 'amount_minor', 'currency', 'is_billable', 'billed_invoice_id', 'receipt_file_id'];

    protected function casts(): array
    {
        return ['spent_on' => 'date', 'amount_minor' => 'integer', 'is_billable' => 'boolean'];
    }

    /** Billable expenses not yet on an invoice, for this invoice's client and currency (and project, when it has one). */
    public function scopeBillableTo($query, Invoice $invoice)
    {
        return $query->where('is_billable', true)->whereNull('billed_invoice_id')->where('client_id', $invoice->client_id)->where('currency', $invoice->currency)
            ->when($invoice->project_id, fn ($q) => $q->where(fn ($q) => $q->where('project_id', $invoice->project_id)->orWhereNull('project_id')));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(File::class, 'receipt_file_id');
    }
}
