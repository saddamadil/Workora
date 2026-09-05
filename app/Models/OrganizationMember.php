<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The join between a person and a company, and the only place a role is stored.
 * The middleware queries this without global scopes — resolving which tenant the
 * request belongs to cannot itself be tenant-scoped.
 */
class OrganizationMember extends Model
{
    use BelongsToOrganization, HasFactory, HasUuids;

    protected $fillable = [
        'organization_id', 'user_id', 'role', 'member_type', 'status',
        'freelancer_category_id', 'default_rate_minor', 'default_rate_currency',
        'internal_notes', 'invited_by', 'invited_at', 'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'default_rate_minor' => 'integer',
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FreelancerCategory::class, 'freelancer_category_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isFreelancer(): bool
    {
        return $this->member_type === 'freelancer';
    }
}
