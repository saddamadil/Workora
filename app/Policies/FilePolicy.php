<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\File;
use App\Models\ProjectMember;
use App\Models\User;
use App\Support\Tenancy;

/**
 * Tenant isolation is the global scope's job; this only decides what a member
 * of the current company may do. Viewers read, everyone else adds and shares,
 * and only owners/admins (or the uploader) remove.
 */
class FilePolicy
{
    public function __construct(private Tenancy $tenancy) {}

    /** Staff see every file in the company; freelancers only their own and their projects'. */
    public function view(User $user, File $file): bool
    {
        if ($this->role() === null) {
            return false;
        }

        // Client logins reach their files through the portal only, never through this policy.
        if ($this->role()->isClient()) {
            return false;
        }

        if (! $this->role()->isFreelancer()) {
            return true;
        }

        return $file->uploaded_by === $user->id
            || ($file->project_id && ProjectMember::where('project_id', $file->project_id)->where('user_id', $user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $this->role() !== null && ! $this->role()->isClient() && $this->role() !== OrganizationRole::Viewer;
    }

    public function update(User $user, File $file): bool
    {
        return $this->create($user) && $this->view($user, $file);
    }

    public function share(User $user, File $file): bool
    {
        return $this->create($user) && $this->view($user, $file);
    }

    public function delete(User $user, File $file): bool
    {
        $role = $this->role();

        if ($role === null || $role === OrganizationRole::Viewer) {
            return false;
        }

        return in_array($role, [OrganizationRole::Owner, OrganizationRole::Admin], true)
            || $file->uploaded_by === $user->id;
    }

    private function role(): ?OrganizationRole
    {
        return $this->tenancy->role();
    }
}
