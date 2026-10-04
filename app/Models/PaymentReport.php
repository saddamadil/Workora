<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A client's note that they have paid an invoice. It changes nothing until the freelancer confirms it. */
class PaymentReport extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'reported_by',
        'amount_minor',
        'method',
        'reference',
        'paid_on',
        'status',
    ];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount_minor' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
