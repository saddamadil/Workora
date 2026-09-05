<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkRequestMessage extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'work_request_id', 'user_id', 'body',
        'proposed_amount_minor', 'proposed_hours', 'proposed_deadline',
    ];

    protected function casts(): array
    {
        return [
            'proposed_amount_minor' => 'integer',
            'proposed_hours' => 'decimal:2',
            'proposed_deadline' => 'date',
        ];
    }

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True when this message changes the terms rather than just talking. */
    public function isCounterOffer(): bool
    {
        return $this->proposed_amount_minor !== null
            || $this->proposed_hours !== null
            || $this->proposed_deadline !== null;
    }
}
