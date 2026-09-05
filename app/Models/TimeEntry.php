<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class TimeEntry extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'timesheet_id', 'project_id', 'task_id',
        'entry_date', 'started_at', 'ended_at', 'minutes', 'description',
        'is_billable', 'rate_minor', 'source', 'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
            'is_billable' => 'boolean',
            'rate_minor' => 'integer',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Once an entry has been invoiced it is part of a financial record.
        static::updating(function (self $entry) {
            if ($entry->getOriginal('locked_at') !== null) {
                throw new RuntimeException('This time entry has been invoiced and cannot be changed.');
            }
        });

        static::deleting(function (self $entry) {
            if ($entry->locked_at !== null) {
                throw new RuntimeException('This time entry has been invoiced and cannot be deleted.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function amountMinor(): int
    {
        return (int) round($this->minutes / 60 * ($this->rate_minor ?? 0));
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
