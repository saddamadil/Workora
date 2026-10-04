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
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id', 'client_id', 'name', 'slug', 'description', 'status',
        'start_date', 'deadline', 'budget_minor', 'currency',
        'project_manager_id', 'created_by', 'billing_model', 'priority', 'tags', 'share_hours',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
            'budget_minor' => 'integer',
            'share_hours' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role_in_project', 'can_view_budget']);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** Used by ProjectPolicy to decide whether a non-privileged user can see this. */
    public const STATUSES = ['planning', 'active', 'on_hold', 'review', 'revision_requested', 'completed', 'cancelled'];

    public const STATUS_LABELS = ['planning' => 'Planning', 'active' => 'Active', 'on_hold' => 'On hold', 'review' => 'In review', 'revision_requested' => 'Revision requested', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    /** Projects this person may see: everything for finance/admin roles, else where they are a member. */
    public function scopeVisibleTo($query, User $user)
    {
        if (app(Tenancy::class)->role()?->seesAllProjects()) {
            return $query;
        }

        return $query->whereHas('members', fn ($m) => $m->where('user_id', $user->id));
    }

    /** Everything this project has cost so far in logged billable time, in minor units. */
    public function trackedCostMinor(): int
    {
        return (int) $this->timeEntries()->where('is_billable', true)->get()
            ->sum(fn (TimeEntry $e) => $e->amountMinor());
    }

    public function hasMember(User|string $user): bool
    {
        $id = $user instanceof User ? $user->id : $user;

        return $this->members()->where('user_id', $id)->exists();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('position');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class)->latest('submitted_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** 0 to 100, from approved tasks. */
    public function progressPercent(): int
    {
        $total = $this->tasks()->where('status', '!=', 'cancelled')->count();

        return $total ? (int) round($this->tasks()->where('status', 'approved')->count() / $total * 100) : 0;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Sum of approved task budgets, in minor units. */
    public function committedMinor(): int
    {
        return (int) $this->tasks()->whereNotNull('budget_minor')->sum('budget_minor');
    }
}
