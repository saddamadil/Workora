<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A user is a person, not a company employee. The same account can be an
 * employee of one company and a freelancer for three others — the role lives
 * on OrganizationMember, never here.
 */
class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'avatar_path', 'phone',
        'country_code', 'timezone', 'locale',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class)->withoutGlobalScopes();
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot(['role', 'member_type', 'status'])
            ->withTimestamps();
    }

    public function freelancerProfile(): HasOne
    {
        return $this->hasOne(FreelancerProfile::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(PayoutMethod::class);
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot(['role_in_project', 'can_view_budget']);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function workRequests(): HasMany
    {
        return $this->hasMany(WorkRequest::class, 'requested_by');
    }

    /** The membership record for a given company, or null if there is none. */
    public function membershipIn(Organization|string $organization): ?OrganizationMember
    {
        $id = $organization instanceof Organization ? $organization->id : $organization;

        return $this->memberships->firstWhere('organization_id', $id);
    }

    public function roleIn(Organization|string $organization): ?OrganizationRole
    {
        return $this->membershipIn($organization)?->role;
    }

    public function belongsToOrganization(Organization|string $organization): bool
    {
        return $this->membershipIn($organization)?->status === 'active';
    }
}
