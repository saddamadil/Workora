<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The freelancer-initiated half of the workflow: they propose work, the company
 * negotiates or approves, and it becomes a task or a project. This is the part
 * of the product most competitors do not have.
 */
class WorkRequest extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'requested_by', 'project_id', 'title', 'description',
        'estimated_hours', 'proposed_amount_minor', 'currency', 'proposed_deadline',
        'status', 'reviewed_by', 'submitted_at', 'reviewed_at', 'response_note',
        'converted_task_id', 'converted_project_id',
    ];

    protected function casts(): array
    {
        return [
            'estimated_hours' => 'decimal:2',
            'proposed_amount_minor' => 'integer',
            'proposed_deadline' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WorkRequestMessage::class)->orderBy('created_at');
    }

    public function convertedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'converted_task_id');
    }

    public function convertedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'converted_project_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'attachable');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['submitted', 'under_review', 'negotiating'], true);
    }

    public function canBeConverted(): bool
    {
        return $this->status === 'approved' && ! $this->converted_task_id && ! $this->converted_project_id;
    }

    /**
     * The amount currently on the table — the latest counter-offer if the two
     * sides have been negotiating, otherwise the freelancer's opening figure.
     */
    public function currentAmountMinor(): ?int
    {
        return $this->messages()
            ->whereNotNull('proposed_amount_minor')
            ->latest()
            ->value('proposed_amount_minor') ?? $this->proposed_amount_minor;
    }
}
