<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Task extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids, SoftDeletes;

    /** The states a task moves through, in order. */
    public const STATUSES = [
        'backlog', 'assigned', 'in_progress', 'submitted',
        'under_review', 'revision_required', 'approved', 'cancelled',
    ];

    public const STATUS_LABELS = [
        'backlog' => 'Backlog', 'assigned' => 'To do', 'in_progress' => 'In progress',
        'submitted' => 'Submitted', 'under_review' => 'In review',
        'revision_required' => 'Needs changes', 'approved' => 'Approved', 'cancelled' => 'Cancelled',
    ];

    /** Tailwind classes for the status pill. */
    public const STATUS_STYLES = [
        'backlog' => 'bg-slate-100 text-slate-600', 'assigned' => 'bg-sky-50 text-sky-700',
        'in_progress' => 'bg-indigo-50 text-indigo-700', 'submitted' => 'bg-amber-50 text-amber-700',
        'under_review' => 'bg-amber-50 text-amber-700', 'revision_required' => 'bg-orange-50 text-orange-700',
        'approved' => 'bg-emerald-50 text-emerald-700', 'cancelled' => 'bg-slate-100 text-slate-400',
    ];

    protected $fillable = [
        'organization_id', 'project_id', 'parent_task_id', 'title', 'description',
        'status', 'priority', 'start_date', 'due_at',
        'estimated_hours', 'actual_hours', 'budget_minor', 'currency',
        'created_by', 'approved_by', 'approved_at', 'position', 'is_internal', 'milestone_id',
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
            'is_internal' => 'boolean',
        ];
    }

    /** Freelancers only see tasks assigned to them; staff see what their project access allows. */
    public function scopeVisibleTo($query, User $user)
    {
        $role = app(Tenancy::class)->role();

        if ($role?->isFreelancer()) {
            return $query->whereHas('assignees', fn ($a) => $a->where('users.id', $user->id));
        }

        if ($role?->seesAllProjects()) {
            return $query;
        }

        return $query->whereHas('project.members', fn ($m) => $m->where('user_id', $user->id));
    }

    /**
     * Replace the assignee list. The pivot table has its own uuid key and a
     * tenant column, so attach() needs them spelled out.
     */
    public function syncAssignees(array $userIds): void
    {
        $orgId = app(Tenancy::class)->idOrFail();
        $current = $this->assignees()->pluck('users.id')->all();

        if ($remove = array_diff($current, $userIds)) {
            $this->assignees()->detach($remove);
        }

        foreach (array_diff($userIds, $current) as $id) {
            $this->assignees()->attach($id, ['id' => (string) Str::uuid(), 'organization_id' => $orgId]);
        }

        $this->unsetRelation('assignees');

        // Assigning someone moves a backlog task to "to do"; removing everyone sends it back.
        if (in_array($this->status, ['backlog', 'assigned'], true)) {
            $this->update(['status' => $userIds ? 'assigned' : 'backlog']);
        }
    }

    /** Recompute logged hours from time entries so the number can never drift. */
    public function refreshActualHours(): void
    {
        $this->update(['actual_hours' => round($this->timeEntries()->sum('minutes') / 60, 2)]);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees->contains('id', $user->id);
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
