<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceItem extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'invoice_id', 'description', 'quantity', 'unit',
        'unit_rate_minor', 'amount_minor', 'source_type', 'source_id', 'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_rate_minor' => 'integer',
            'amount_minor' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->amount_minor = (int) round((float) $item->quantity * $item->unit_rate_minor);
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** The task, milestone or timesheet this line was generated from. */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
