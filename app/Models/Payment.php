<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record of money owed, scheduled, or moved.
 *
 * This application tracks payments; it does not process them. Settlement happens
 * through a licensed processor, and `processor_reference` is the only link back
 * to it. Nothing here should ever hold account or card credentials.
 */
class Payment extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'invoice_id', 'user_id', 'payout_method_id',
        'amount_minor', 'currency', 'method', 'status', 'scheduled_for', 'paid_at',
        'reference', 'processor', 'processor_reference', 'failure_reason',
        'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'scheduled_for' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(PayoutMethod::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isSettled(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'scheduled', 'processing'], true);
    }
}
