<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Append-only. A database trigger rejects any UPDATE or DELETE, and the guards
 * below fail earlier with a clearer message. If you ever find yourself wanting
 * to edit an audit row, the answer is a new row.
 */
class AuditLog extends Model
{
    use BelongsToOrganization, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'user_id', 'action', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'project_id', 'client_id',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new RuntimeException('Audit log entries cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Record an action. Call this from services, not from model observers. */
    /**
     * Record an action. The project and client are picked up from the subject when it has them, so
     * a project's timeline can be built without every call site spelling them out.
     */
    public static function record(string $action, ?Model $subject = null, array $changes = []): self
    {
        $projectId = $changes['project_id'] ?? ($subject instanceof Project ? $subject->id : ($subject?->getAttributes()['project_id'] ?? null));
        $clientId = $changes['client_id'] ?? ($subject instanceof Client ? $subject->id : ($subject?->getAttributes()['client_id'] ?? null));

        return static::create([
            'project_id' => $projectId,
            'client_id' => $clientId,
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $changes['old'] ?? null,
            'new_values' => $changes['new'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
