<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklistItem extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = ['organization_id', 'task_id', 'title', 'is_done', 'position'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'position' => 'integer'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
