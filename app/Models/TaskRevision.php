<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A structured change request against a submission — one issue, one location,
 * one status. Deliberately not a free-text comment, so "what is still
 * outstanding" is a query rather than a reading exercise.
 */
class TaskRevision extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'task_id', 'task_submission_id', 'raised_by', 'number',
        'issue', 'location', 'priority', 'comment', 'status', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['number' => 'integer', 'resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $revision) {
            $revision->number ??= static::where('task_id', $revision->task_id)->max('number') + 1;
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(TaskSubmission::class, 'task_submission_id');
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_progress'], true);
    }
}
