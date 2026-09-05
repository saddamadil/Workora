<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    public const TYPES = ['hourly', 'fixed', 'milestone', 'retainer'];

    protected $fillable = [
        'organization_id', 'user_id', 'project_id', 'reference', 'title', 'type', 'currency',
        'hourly_rate_minor', 'fixed_amount_minor', 'retainer_amount_minor', 'max_hours_per_cycle',
        'payment_cycle', 'payment_terms_days', 'starts_on', 'ends_on', 'status', 'terms',
        'document_path', 'sent_at', 'accepted_at', 'accepted_ip',
        'terminated_at', 'termination_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate_minor' => 'integer',
            'fixed_amount_minor' => 'integer',
            'retainer_amount_minor' => 'integer',
            'max_hours_per_cycle' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'terminated_at' => 'datetime',
        ];
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ContractMilestone::class)->orderBy('position');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->starts_on->isPast()
            && (! $this->ends_on || $this->ends_on->isFuture());
    }

    public function isHourly(): bool
    {
        return in_array($this->type, ['hourly', 'retainer'], true);
    }

    /** The full contract value in minor units, where the type defines one. */
    public function valueMinor(): ?int
    {
        return match ($this->type) {
            'fixed' => $this->fixed_amount_minor,
            'milestone' => (int) $this->milestones()->sum('amount_minor'),
            'retainer' => $this->retainer_amount_minor,
            default => null, // hourly has no fixed ceiling
        };
    }
}
