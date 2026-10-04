<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ProjectMilestone extends Model
{
    use BelongsToOrganization, HasUuids;

    public const STATUSES = ['upcoming' => 'Upcoming', 'in_progress' => 'In progress', 'completed' => 'Completed'];

    protected $fillable = [
        'organization_id',
        'project_id',
        'title',
        'description',
        'due_date',
        'status',
        'position',
        'completed_at',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime', 'position' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
