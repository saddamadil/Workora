<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids, SoftDeletes;

    /** The states a task moves through, in order. */
    public const STATUSES = [
        'backlog', 'assigned', 'in_progress', 'submitted',
        'under_review', 'revision_required', 'approved', 'cancelled',
    ];

    protected $fillable = [
        'organization_id', 'project_id', 'parent_task_id', 'title', 'description',
        'status', 'priority', 'start_date', 'due_at',
        'estimated_hours', 'actual_hours', 'budget_minor', 'currency',
        'created_by', 'approved_by', 'approved_at', 'position',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_at' => 'datetime',
            'approved_at' => 'datetime',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
            'budget_minor' => 'integer',
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->whereNull('parent_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position');
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'depends_on_task_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(TaskSubmission::class)->orderByDesc('attempt');
    }

    public function latestSubmission(): BelongsTo|HasMany
    {
        return $this->hasMany(TaskSubmission::class)->orderByDesc('attempt')->limit(1);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TaskRevision::class);
    }

    public function openRevisions(): HasMany
    {
        return $this->revisions()->whereIn('status', ['open', 'in_progress']);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'attachable');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isOverdue(): bool
    {
        return $this->due_at
            && $this->due_at->isPast()
            && ! in_array($this->status, ['approved', 'cancelled'], true);
    }

    /** A task can only be submitted from a state where work is in progress. */
    public function canBeSubmitted(): bool
    {
        return in_array($this->status, ['assigned', 'in_progress', 'revision_required'], true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['approved', 'cancelled'], true);
    }
}
