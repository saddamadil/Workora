<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ClientRequest extends Model
{
    use BelongsToOrganization, HasUuids;

    public const STATUSES = ['new' => 'New', 'discussing' => 'Discussing', 'accepted' => 'Accepted', 'in_progress' => 'In progress', 'completed' => 'Completed', 'declined' => 'Declined'];

    protected $fillable = [
        'organization_id',
        'client_id',
        'project_id',
        'requested_by',
        'title',
        'description',
        'priority',
        'preferred_deadline',
        'status',
        'response_note',
        'task_id',
        'converted_project_id',
    ];

    protected function casts(): array
    {
        return ['preferred_deadline' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function files(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(File::class, 'attachable');
    }
}
