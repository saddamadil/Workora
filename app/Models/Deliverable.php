<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Deliverable extends Model
{
    use BelongsToOrganization, HasUuids;

    public const LABELS = ['in_review' => 'Ready for review', 'approved' => 'Approved', 'changes_requested' => 'Changes requested'];

    protected $fillable = [
        'organization_id',
        'project_id',
        'task_id',
        'milestone_id',
        'title',
        'description',
        'status',
        'submitted_by',
        'submitted_at',
        'decided_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeliverableReview::class)->oldest('created_at');
    }

    public function files(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(File::class, 'attachable');
    }
}
