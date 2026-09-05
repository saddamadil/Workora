<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timesheet extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'contract_id', 'period_start', 'period_end',
        'status', 'total_minutes', 'total_amount_minor', 'currency',
        'submitted_at', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_minutes' => 'integer',
            'total_amount_minor' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    public function entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderBy('entry_date');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    /**
     * Recalculate totals from the entries. Always derive rather than trusting a
     * submitted figure — the stored total is a cache, not the source of truth.
     */
    public function recalculate(): void
    {
        $entries = $this->entries()->where('is_billable', true)->get();

        $this->total_minutes = (int) $entries->sum('minutes');
        $this->total_amount_minor = (int) $entries->sum(
            fn (TimeEntry $entry) => (int) round($entry->minutes / 60 * ($entry->rate_minor ?? 0))
        );

        $this->save();
    }

    public function totalHours(): float
    {
        return round($this->total_minutes / 60, 2);
    }
}
