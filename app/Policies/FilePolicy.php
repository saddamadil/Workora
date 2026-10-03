<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\File;
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

    public function create(User $user): bool
    {
        return $this->role() !== null && $this->role() !== OrganizationRole::Viewer;
    }

    public function update(User $user, File $file): bool
    {
        return $this->create($user);
    }

    public function share(User $user, File $file): bool
    {
        return $this->create($user);
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
